<?php
//Include the task manager into our program
require_once("./taskmanager/taskmanager.php");

//Initialize the Manager
$manager=new TaskManager(false);
$manager->sleep=2;
$manager->sim_par=5;
$manager->verbose=false;

$dbinfo=array("user"=>"root","pass"=>"","host"=>"localhost","db"=>"pits");

//Load the tasks
$manager->add_task(new Init($dbinfo),false);
$manager->add_task(new AnywhoSpider(false),false);
$manager->add_task(new MyspaceSpider(),false);
$manager->add_task(new FacebookSpider(),false);
$manager->add_task(new Clean(),false);

//Make sure there are tasks present
if($manager->check_tasks()){
	//Run all loaded tasks
	$manager->run();
}
?>
