<?php
/**
 * MyspaceSpider
 *
 * @package
 * @author Darkvengance
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class MyspaceSpider extends Task{
	public $url;
	public $myspace;
	public $data;

	/**
	 * MyspaceSpider::construct_search()
	 * * Constructs the search URL *
	 * @return NULL
	 */
	public function construct_search($searchterm,$location,$distance=25,$gender="both",$startpage=1,$count=10,$format="xml",$minage=18,$maxage=99,$searchby="name"){

		//Initialize the parameters
		$parameters=array(
		"searchTerms"=>$searchterm,
		"count"=>$count,
		"distance"=>$distance,
		"format"=>$format,
		"gender"=>$gender,
		"location"=>$location,
		"minAge"=>$minage,
		"maxAge"=>$maxage,
		"searchBy"=>$searchby,
		"startPage"=>$startpage
		);

		//Start the search URL using myspace's search API
		$url="http://api.myspace.com/opensearch/people";

		//Apply the parameters to the search URL
		$firstrun=1;
		foreach($parameters as $key=>$value){
			if($firstrun==1){
				$url=$url."?".$key."=".$value;
			}else{
				$url=$url."&".$key."=".$value;
			}
			$firstrun=0;
		}

		$this->url=$url;
	}

	/**
	 * MyspaceSpider::search()
	 * * Performs the actual search *
	 * @return bool
	 */
	public function search(){
		$result=file_get_contents($this->url);
		$results=$this->parse_results($result);
		$results["profiles"]=$this->get_profiles($results);

		$data=serialize($results);

		$this->data=$data;
		return TRUE;
	}

	/**
	 * MyspaceSpider::parse_results()
	 * * Parses results returned from search and puts it into a nice array *
	 * @return array
	 */
	public function parse_results($results){
		$myspace=new SimpleXMLElement($results);

		$totalResults=$myspace->totalResults; //Total number of search results
		$startindex=$myspace->startIndex;     //Page we are on now
		$itemsperpage=$myspace->itemsPerPage; //Number of entries perpage
		$resultcount=$myspace->resultCount;   //Result Count
		$searchid=$myspace->searchId;         //Unique myspace API search ID

		$values=array("total"=>$totalResults,
		"index"=>$startindex,
		"ipp"=>$itemsperpage,
		"count"=>$resultcount,
		"id"=>$searchid);

		$people=array();

		foreach($myspace->entry as $entry){
			$userid=str_replace("myspace.com.person.","",$entry->id); //User ID
			$displayname=$entry->displayName; //Display Name
			$profile=$entry->profileUrl;      //Profile URL
			$picture=$entry->thumbnailUrl;    //Picture URL
			$gender=$entry->gender;           //Gender
			$age=$entry->age;                 //Age
			$location=$entry->location;       //Physical Location
			$updated=$entry->updated;         //Last Login
			$person=array("id"=>$userid,
			"displayname"=>$displayname,
			"profileurl"=>$profile,
			"thumburl"=>$picture,
			"gender"=>$gender,
			"age"=>$age,
			"location"=>$location,
			"updated"=>$updated);
			array_push($people,$person);
		}

		$return=array("metadata"=>$values,"results"=>$people);
		return $return;
	}

	/**
	 * MyspaceSpider::get_profiles()
	 * * Grabs the profile data for each user *
	 * @return array
	 */
	public function get_profiles($data){
		$profile=array();
		foreach($data["results"] as $entry){
			$url=$entry["profileurl"];
			$id=$entry["id"];
			$data=file_get_contents($url);
			$profile[]=array("id"=>$id,"profile"=>$data);
		}
		return $profile;
	}

	/**
	 * MyspaceSpider::insert_data()
	 * * Insert data into the database *
	 * @return bool
	 */
	public function insert_data(){
		//Code to insert data from $this->data
	}

	/**
	 * MyspaceSpider::run()
	 * * Runs the process *
	 * @return NULL
	 */
	public function run(){
		$this->construct_search();
		$this->search();
		$this->insert_data();
	}
}

?>