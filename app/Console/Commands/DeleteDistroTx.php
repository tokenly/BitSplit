<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Models\Distribution as Distro, Models\DistributionTx as DistroTx;
use Models\Fuel;

class DeleteDistroTx extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bitsplit:deleteDistroTx {address} {out_address}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Removes an entry from a distributions address list and recalculates total';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //find distro
        $address = $this->argument('address');
        $out_address = $this->argument('out_address');
        $distro = Distro::where('deposit_address', $address)->first();
        if(!$distro){
            $distro = Distro::where('id', intval($address))->first();
        }
        if(!$distro){
            $this->error('Distribution not found');
            return false;
        }
        //get tx list
        $tx_list = DistroTx::where('distribution_id', $distro->id)->orderBy('id', 'asc')->get();
        //check for matching entry
        $found = false;
        if($tx_list){
            foreach($tx_list as $item){
                if($item->destination == $out_address OR $item->id == $out_address){
                    $found = $item;
                    break;
                }
            }
        }
        if(!$found){
            $this->error('Distribution TX not found');
            return false;
        }
        $new_count = count($tx_list) - 1;
        if($new_count <= 0){
            $this->error('Distribution must have at least one address to send to');
            return false;
        }
        $quantity = $found->quantity;
        
        //delete tx
        $delete = $found->delete();
        if(!$delete){
            $this->error('Error deleting distribution tx #'.$found->id);
            return false;
        }
        
        //update totals;
        $distro->asset_total = intval($distro->asset_total) - intval($quantity);
        $distro->fee_total = Fuel::estimateFuelCost($new_count, $distro);
        $save = $distro->save();
        if(!$save){
            $this->error('Error updating distribution #'.$distro->id.' totals');
            return false;
        }
        $this->info('Deleted tx #'.$found->id.' from distro #'.$distro->id);
        return true;
    }
}
