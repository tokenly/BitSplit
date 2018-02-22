@extends('app')

@section('content')
<div class="row">
	<div class="col-lg-6">
		<h1>Distribute Tokens</h1>
        <p>
            Use this tool to distribute Counterparty tokens to participating Folding@Home users based on their folding contributions in the given time period. 
        </p>
		<p>
			A deposit address will be generated for you along with a total amount of tokens + 
			total amount of <em>fuel</em> (bitcoin) it will cost. Fuel can be paid directly, 
			or sourced from your account <em>fuel address</em>. Once confirmed, your tokens will enter
			the distribution process.
		</p>
        <p>
            <strong>Participating folders:</strong> {{ number_format(\App\Models\DailyFolder::countUniqueFolders(true)) }}
        </p>
        <p class="text-danger">
            <strong>Attention:</strong> Transaction capacity on the Bitcoin network is at high levels of congestion.
            If your distribution is time sensitive at all, please make sure to double check that your miner fee rate
            is set appropriately, otherwise you may be stuck with a several day wait time.<br>
            You can use <a href="https://bitcoinfees.21.co/" target="_blank">https://bitcoinfees.21.co/</a> to help
            with estimations, or if unsure you can email <a href="mailto:team@tokenly.com">team@tokenly.com</a> for a recommendation.
        </p>
		<hr>
		<div id="new-distro-form">
			<form action="{{ route('distribute.post') }}" method="post" enctype="multipart/form-data">
				<input type="hidden" name="_token" value="{{ csrf_token() }}" />
				<div class="form-group">
					<label for="asset">Token Name</label>
					<input type="text" class="form-control" id="asset" name="asset" placeholder="(e.g LTBCOIN)" value="{{ old('asset') }}" required />
				</div>
				<div class="form-group" id="percent_asset_total">
					<label for="asset_total">Total Tokens to Send</label>
					<input type="text" class="form-control numeric-only" id="asset_total" name="asset_total" value="{{ old('asset_total') }}" placeholder="" />
				</div>
				<div class="form-group checkbox">
					<input type="checkbox" style="margin-left: 10px; margin-top: 2px;" name="use_fuel" id="use_fuel" value="1" checked="checked" />
					<label for="use_fuel" style="padding-left: 35px; font-size: 13px;" >Use available fuel for BTC fee?</label>
				</div>
				<div class="form-group dropdown">
					<label for="calculation_type">Calculation Type</label>
					<select name="calculation_type" id="calculation_type" class="form-control">
						<option value="even">Even</option>
						<option value="static">Static</option>
					</select>
				</div>
				<div class="form-group dropdown">
					<label for="distribution_class">Distribution Class</label>
					<select name="distribution_class" id="distribution_class" class="form-control">
						<option value="All Folders">All Folders</option>
						<option value="Minimum FAH points">Minimum FAH points</option>
						<option value="Top Folders">Top Folders</option>
						<option value="Random">Random</option>
					</select>
				</div>
				<div id="minimum_fah_points_wrapper" class="form-group" style="display: none;">
					<label for="minimum_fah_points">Minimum Required FAH Points (New credit)</label>
					<input type="text" min="0" name="minimum_fah_points" id="minimum_fah_points" class="form-control">
				</div>
				<div id="amount_top_folders_wrapper" class="form-group" style="display: none;">
					<label for="amount_top_folders">Amount of Top Folders to Select</label>
					<input type="number" min="3" value="100" name="amount_top_folders" id="amount_top_folders" class="form-control">
				</div>
				<div id="amount_random_folders_wrapper" style="display: none;">
					<div class="form-group">
						<label for="amount_random_folders">Amount of Random Folders to Select</label>
						<input type="number" min="3" name="amount_random_folders" id="amount_random_folders" class="form-control">
					</div>
					<div class="form-group checkbox">
						<input type="checkbox" style="margin-left: 10px; margin-top: 2px;" name="weight_cache_by_fah" id="weight_cache_by_fah" value="1" />
						<label for="weight_cache_by_fah" style="padding-left: 35px; font-size: 13px;" >Weight chance by FAH points?</label>
					</div>
				</div>
				<div class="form-group">
					<label for="folding_start_date">Folding Start Date</label>
					<input type="text" id="folding_start_date" name="folding_start_date" value="{{ old('folding_start_date') }}" class="form-control datetimepicker_folding" />
                </div>
                <div class="form-group">
					<label for="folding_end_date">Folding End Date</label>
					<input type="text" id="folding_end_date" name="folding_end_date" value="{{ old('folding_end_date') }}" class="form-control datetimepicker_folding" />
				</div>  
				<div class="form-group">
					<label for="btc_fee_rate">Custom Miner Fee Rate</label>
					<input type="text" class="form-control" id="btc_fee_rate" name="btc_fee_rate" placeholder="{{ Config::get('settings.miner_satoshi_per_byte') }}" />
					<small>
						* This is an advanced feature. Rates are defined in <em>satoshis per byte</em>, enter a number between {{ Config::get('settings.min_fee_per_byte') }} and {{ Config::get('settings.max_fee_per_byte') }}.<br>
                        See <a href="https://bitcoinfees.21.co/" target="_blank">https://bitcoinfees.21.co/</a> for help determining a rate.
					</small>
				</div>
				<div class="form-group">
					<label for="btc_dust_override">Custom BTC Dust Size</label>
					<input type="text" class="form-control" id="btc_dust_override" name="btc_dust_override" placeholder="0.00005430" />
					<small>
						* This is an advanced feature.  Enter a value here to override the standard dust size of 0.00005430 BTC.
					</small>
				</div>	
				<div class="form-submit">
					<button type="submit" class="btn btn-lg btn-success"><i class="fa fa-check"></i> Initiate Distribution</button>
				</div>															
			</form>
		</div>
	</div>
	@include('inc.dash-sidebar')
</div>
@endsection

@section('title')
	Bitsplit Dashboard
@stop
