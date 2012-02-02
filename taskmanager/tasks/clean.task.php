<?php

class Clean extends Task{
	public function run(){
		parent::share_memory("ERAS");
	}
}


?>