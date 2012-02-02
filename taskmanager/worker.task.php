<?php
/**
 * Task
 *
 * @package PTM
 * @author Darkvengance
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class Task {
	protected $pid;
	protected $ppid;
	protected $memory;

	public $verbose;

	public function __construct(){
		$this->memory=array();
	}

	/**
	 * Task::fork()
	 * * Runs the task assigned to it *
	 * @return
	 */
	public function fork($fork=FALSE){

		if($fork==TRUE){
			if($this->verbose==true){
				echo "Worker: Forking enabled for current task.\n";
			}
			$pid=pcntl_fork();

			if($pid==-1){
				throw new Exception('fork error on Task object');
			}elseif($pid){
				#parent class
				$this->pid=$pid;
			}else{
				#child class
				$this->get_ids();
				$this->run();
				exit(1);
			}
		}else{
			if($this->verbose==true){
				echo "Worker: Forking disabled for current task.\n";
			}
			$this->get_ids();
			$this->run();
		}
	}

	/**
	 * Task::get_ids()
	 * * Collects the process IDs *
	 * @return
	 */
	public function get_ids(){
		#child
		$this->ppid=posix_getppid();
		$this->pid=posix_getpid();
	}

	/**
	 * Task::pid()
	 * * Returns the process ID *
	 * @return int
	 */
	public function pid(){
		return $this->pid;
	}

	/**
	 * Task::share_memory()
	 * * Interacts with the taskmanager's internal memory *
	 * @return mixed
	 */
	public function share_memory($method="COUN",$arg1="",$arg2=""){
		$task_name=get_class($this);
		if($this->verbose==true){
			echo "Worker: Accessing shared memory. Method:".$method."\n";
		}

		switch($method){

			case "CLEA":
				if(unset($this->memory[$task_name])){
					return true;
				}else{
					return false;
				}
			break;

			case "COUN":
				$t=count($this->memory);
				$i=count($this->memory, COUNT_RECURSIVE);
				$count=$i-$t;
				return $count;
			break;

			case "ERAS":
				if(unset($this->memory)){
					$this->memory=array();
					return true;
				}else{
					return false;
				}
			break;

			case "GET":
				if($arg1=="self"||$arg1=="this"){
					$arg1=$task_name;
				}
				if(array_key_exists($arg2,$this->memory[$arg1])){
					$data=$this->memory[$arg1][$arg2];
					return $data;
				}else{
					return false;
				}
			break;

			case "POST":
				if(isset($arg2)){
					$this->memory[$task_name][$arg2]=$arg1;
					$key=$arg2;
				}else{
					$this->memory[$task_name][]=$arg1;
					$key=array_search($arg1,$this->memory[$task_name]);
				}
				return $key;
			break;

			case default:
				die("Undefined method called!");
			break;
		}
	}

}
?>