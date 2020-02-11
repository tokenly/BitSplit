<?php
namespace Models;
use App\Libraries\Substation\Substation;
use App\Libraries\Substation\UserWalletManager;
use DB, Models\Distribution, Models\DistributionTx, User, UserMeta, Exception, Log, Config;
use Tokenly\CryptoQuantity\CryptoQuantity;
class Fuel
{

	public static function pump($userId, $destination_address, $amount, $asset = 'BTC', $fee = null, $amount_is_in_satoshis = true)
	{
        $user = null;
		if($userId == 'MASTER'){
			$fuel_address_uuid = env('MASTER_FUEL_ADDRESS_UUID');
			if(!$fuel_address_uuid){
				Log::error('Fuel pump - master fuel address not found');
				throw new Exception('Master fuel address not found');
			}
		}
		elseif(strpos($userId, 'distro:') === 0){
			$userId = substr($userId, 7);
			$get = Distribution::where('id', $userId)->orWhere('deposit_address', $userId)->first();
			if(!$get){
				Log::error('Fuel pump - distro not found '.$userId);
				throw new Exception($userId.' distribution not found');
			}
			$fuel_address_uuid = $get->address_uuid;
			$user = User::find($get->user_id);
		}
		else{
			$user = User::where('id', $userId)->orWhere('username', $userId)->first();
			if(!$user){
				Log::error('Fuel pump - user not found '.$userId);
				throw new Exception($userId.' user not found');
			}
			$fuel_address_uuid = UserMeta::getMeta($user->id, 'fuel_address_uuid');
			if(!$fuel_address_uuid){
				Log::error('Fuel pump - fuel address not found '.$userId);
				throw new Exception($userId.' fuel address not found');
			}
		}
		if($destination_address == 'MASTER'){
			$destination_address = env('MASTER_FUEL_ADDRESS');
		}
		elseif($destination_address == 'HOUSE'){
			$destination_address = env('HOUSE_INCOME_ADDRESS');
			if(!$destination_address){
				Log::error('Fuel pump - house income address not found');
				throw new Exception('House income address not found');
			}
		}
		elseif(strpos($destination_address, 'user:') === 0){
			$destination_address = substr($destination_address, 5);
			$user = User::where('id', $destination_address)->orWhere('username', $destination_address)->first();
			if(!$user){
				Log::error('Fuel pump - user not found '.$destination_address);
				throw new Exception($destination_address.' user not found');
			}
			$destination_address = UserMeta::getMeta($user->id, 'fuel_address');
			if(!$destination_address){
				Log::error('Fuel pump - fuel address not found '.$user->id);
				throw new Exception($user->id.' fuel address not found');
			}
		}
		else{
			if(is_int($destination_address)){
				$distro = Distribution::where('id', $destination_address)->first();
				if($distro){
					$destination_address = $distro->deposit_address;
				}
			}
		}
		if(strtolower($amount) == 'sweep'){
            throw new Exception("Sweeping is not implemented", 1);
			// Log::info('Sweeping assets from '.$fuel_address_uuid.' to '.$destination_address);
		}
		if ($amount_is_in_satoshis) {
            $destination_quantity = CryptoQuantity::fromSatoshis($amount);
        } else {
			$destination_quantity = CryptoQuantity::fromFloat($amount);

        }

        // fee is ignored for fuel pump
        // if($fee === null){
        //     $per_byte = Config::get('settings.miner_satoshi_per_byte');
        // }
        // else{
        //     $per_byte = $fee;
        // }

        // ensure user and wallet id
        if (!$user) {
            throw new Exception("Fuel pump requires a user", 1);
        }
        $wallet_uuid = app(UserWalletManager::class)->ensureSubstationWalletForUser($user);

        $substation = Substation::instance();
        $send_parameters = [
            // let substation pick a low-ish value for fuel
            'feeRate' => 'medlow',
        ];
        Log::info('Pumping '.$destination_quantity.' '.$asset.' from '.$fuel_address_uuid.' to '.$destination_address.' (fee rate: '.$send_parameters['feeRate'].')');
        return $substation->sendImmediatelyToSingleDestination($wallet_uuid, $fuel_address_uuid, $asset, $destination_quantity, $destination_address, $send_parameters);
	}

	public static function masterFuelSwap($userId, $token_in, $token_out, $in_amount, $out_amount)
	{
        throw new Exception("masterFuelSwap is unimplemented", 1);

		$output = array();
		Fuel::pump('MASTER', 'user:'.$userId, Config::get('settings.miner_fee'), 'BTC'); //pump a bit of BTC to pay for TX fee
		$output['in_swap'] = Fuel::pump($userId, 'MASTER', $in_amount, $token_in);
		$output['out_swap'] = Fuel::pump('MASTER', 'user:'.$userId, $out_amount, $token_out);
		if($output['in_swap'] AND $output['out_swap']){
			Log::info('Swapped fuel with master - user:'.$userId.' '.$output['in_swap']['txid'].' -> '.$output['out_swap']['txid']);
		}
		return $output;
	}

	public static function getFuelQuote($token, $amount, $amount_is_in_satoshis = true)
	{
		$valid_assets = Config::get('settings.valid_fuel_tokens');
		if(!isset($valid_assets[$token])){
			return false;
		}
		$rate = $valid_assets[$token];
		$quotebot = json_decode(@file_get_contents(env('QUOTEBOT_URL')), true);
		if(!is_array($quotebot)){
			return false;
		}
		$usd_rate = false;
		foreach($quotebot['quotes'] as $row){
			if($row['source'] == 'bitcoinAverage' AND $row['pair'] == 'USD:BTC'){
				$usd_rate = $row['lastHigh'];
			}
		}
		if(!$usd_rate){
			return false;
		}
		$btc_amount = round($rate / $usd_rate, 8);
		if($amount_is_in_satoshis){
			$amount = $amount / 100000000;
		}
		$quote = round($amount * $btc_amount, 8);
		if($quote <= 0.000055){
			//too dusty
			return false;
		}
		if($amount_is_in_satoshis){
			$quote = intval($quote * 100000000);
		}
		return $quote;
	}

	public static function estimateFuelCost($tx_count, Distribution $distro)
	{
        return self::calculateFuel($tx_count, $distro->fee_rate, $distro->getBTCDustSatoshis());
	}

    public static function calculateFuel($tx_count, $fee_rate = null, $dust_size = null)
    {
        //load settings
        $per_byte = Config::get('settings.miner_satoshi_per_byte');
        if($fee_rate != null){
            $per_byte = $fee_rate;
        }
		$max_txos = Config::get('settings.max_tx_outputs');
        if($dust_size == null){
            $dust_size = 0;
        }
        $extra_bytes = Config::get('settings.tx_extra_bytes');
        $input_bytes = Config::get('settings.tx_input_bytes');
        $xcp_tx_bytes = Config::get('settings.xcp_tx_bytes');
        $average_txo_bytes = Config::get('settings.average_txo_bytes');
        $service_fee = Config::get('settings.distribute_service_fee');

		//base cost for # of transactions they are making
		$base_cost = ceil(($per_byte * $xcp_tx_bytes) * $tx_count); //get base amount of satoshis that will be used for distro fees
        $base_cost += ceil($tx_count * $dust_size); //add on dust output values

        //tack on platform service fee
        $service_cost = ($service_fee * $tx_count);

        //figure out how many utxos we need to make
        $num_primes = ceil($tx_count / $max_txos);
		$txos_per_prime = ceil($tx_count / $num_primes);
        if($num_primes === 1){
            $txos_per_prime++; //add 1 for change output that has service fee
        }

        //calculate base prime cost
        $prime_size = $input_bytes + $extra_bytes + ($txos_per_prime * $average_txo_bytes);
        $prime_cost = 0;
		for($i = 1; $i <= $num_primes; $i++){
			$prime_cost += $prime_size * $per_byte;
		}

		//cost for priming the priming transactions (if applicable)
		$pre_prime_cost = 0;
        $pre_prime_size = 0;
		if($num_primes > 1){
			$pre_prime_size = $input_bytes + $extra_bytes + (($num_primes+1) * $average_txo_bytes);
			$pre_prime_cost = $pre_prime_size * $per_byte;
		}

        //add up total BTC fuel costs
		$cost = intval($base_cost + $service_cost + $prime_cost + $pre_prime_cost);

        //add some buffer for each priming transaction to cover potential fee variations
        $cost += ceil(Config::get('settings.miner_fee') * $num_primes);

        //add a little bit extra to cover cleanup transactions
        $btc_cleanup = ($input_bytes + $extra_bytes + $average_txo_bytes) * $per_byte;
        $xcp_cleanup = ($xcp_tx_bytes * $per_byte) + $dust_size;

        $cost += $btc_cleanup;
        $cost += $xcp_cleanup;

        return $cost;
    }
}
