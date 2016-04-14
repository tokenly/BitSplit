<?php
namespace Distribute;
use Models\Distribution as Distro;

class Processor
{
	function __construct()
	{
		$this->initializer = new Initialize;
	}
	
	public function processDistributions()
	{
		$get = Distro::where('complete', 0)->where('hold', 0)->get();
		if(!$get OR count($get) == 0){
			//nothing to do
			return false;
		}
		foreach($get as $k => $row){
			$this->processStage($row);
		}
		return true;
	}
	
	protected function processStage($distro)
	{
		if($distro->stage == 0){
			$this->initializer->init($distro);
			return true;
		}
		$stage = $distro->stageName();
		if(!$stage){
			return false;
		}
		$stage = 'Stages\\'.$stage;
		$load = new $stage($distro);
		$init = $load->init();
		return true;
	}
}
