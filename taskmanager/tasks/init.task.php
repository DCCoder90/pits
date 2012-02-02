<?php

class Init extends Task{
	public $dbinfo;

	public function __construct($dbinfo){
		$this->dbinfo=$dbinfo;
	}

	public function run(){
		parent::share_memory("POST",$this->dbinfo);
	}
}


?>