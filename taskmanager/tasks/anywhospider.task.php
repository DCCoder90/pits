<?php
define("CHECK_IF_EXISTS",false);
define("PATTERN_RESULT_ITEM",'/<td\s+class=\"nameAndAddress\">(.*)<\/td>/smU');
define("PATTERN_NAME",'/<a\s+class=\"resultName\"\s+href=\"[^\"]*\">([^<]*)<\/a>/smU');
define("PATTERN_ADDRESS",'/<div\s+class=\"listingInfo\">\s+<div>([^<]*)<\/div>/smU');
define("PATTERN_PHONE",'/<div\s+class=\"phone\">([^<]*)<\/div>/smU');
define("PATTERN_PAGE_NEXT",'/<a\s+class=\"L6\"\s+href=\"([^\"]*)\"><font[^>]*>Next/smU');

/**
 * AnywhoSpider
 *
 * @package PITS - Personal Information Tracker/Spider
 * @author Darkvengance
 * @copyright Copyright (c) 2011
 * @version $Id$
 * @access public
 */
class AnywhoSpider extends Task{
	public $crawled;
	public $cache;
	public $limit;
	public $url;
	public $links;
	public $banned_ext;
	public $cache_clean;
	public $link_exp='#href="(https?://[&=a-zA-Z0-9-_./]+)"#si';
	public $db_conn;
	public $banned;

	public $holder;
	public $state;

	public $share;


	public function __construct($share=false){
		$this->limit = 15;
		$this->banned_ext=array(
	".dtd",".css",".xml",
	".js",".gif",".jpg",
	".jpeg",".bmp",".ico",
	".rss",".pdf",".png",
	".psd",".aspx",".jsp",
	".srf",".cgi",".exe",
	".cfm");

		$this->share=$share;

		$this->link_exp='<a\s*href=[\'|"](http://whitepages\.anywho\.com/results\.php\?ReportType=.*&qn=.*&qs=.*&qi=.*&qk=.*?)[\'"].*?>';

		$this->db_conn=new mysqli("localhost", "user", "password", "db");

	}

	public function __destruct(){
		unset($this->cache,$url,$crawl,$text,$document,$html,$hyperlink);
		unset($this->holder,$clean_info,$anystuff,$morestuff,$info_array,$address,$phone,$name);
	}

	/**
	 * AnywhoSpider::html2txt()
	 * * Strips out un-needed information *
	 * @return string
	 */
	public function html2txt($document){
		$search = array(
		'/^.*<BODY.*?>/si',
		'/<\/BODY>.*/si',
		'@<script[^>]*?>.*?</script>@si',// Strip out javascript
		'@<style[^>]*?>.*?</style>@siU',// Strip style tags properly

		'@<![\s\S]*?--[ \t\n\r]*>@'// Strip multi-line comments including CDATA
		);
		$text = preg_replace($search, '', $document);
		return $text;
	}

	/**
	 * AnywhoSpider::is_valid_ext()
	 * * Checks if the page has a valid extension *
	 * @return bool
	 */
	public function is_valid_ext($url){
		foreach( $this->banned_ext as $ext ){
			if( $ext == substr( $url, strlen($url) - strlen( $ext ) ) ) return false;
		}
		return true;
	}

	/**
	 * AnywhoSpider::is_crawled()
	 * * Checks if the page has already been crawled *
	 * @return bool
	 */
	public function is_crawled($url){
		return in_array( $url, $this->crawled );
	}

	/**
	 * AnywhoSpider::_crawl()
	 * * Crawls the page *
	 * @return bool
	 */
	public function _crawl($url,&$items){
		//Added check for the available fopen wrappers (excluding sockets)
		if(in_array(ini_get("allow_url_fopen"),array("On","on","1"))){
			//If url_fopen wrapper is enabled on the server
			$this->cache = @file_get_contents($url);
		}else{
			if(function_exists("curl_init")){
				//Use curl
				$ch = curl_init();
				curl_setopt ($ch, CURLOPT_URL, $url);
				curl_setopt ($ch, CURLOPT_HEADER, 0);
				curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
				if ($referrer)
				{
					curl_setopt ($ch, CURLOPT_REFERER, $referrer);
				};
				$result = curl_exec ($ch);
				curl_close ($ch);
				$this->cache=trim($result);
			}else{
				//There are no file open functions enabled!
				return false;
			}
		}
		if (!$this->cache){
			return false;
		}
		$this->crawled[] = urldecode($url) ;
		//Preprocess the results page so the regex pattersn won't break'
		$this->cache=str_replace("'",'"',$this->cache);
		$this->cache=str_replace("<br>",'LINEBREAK',$this->cache);
		$this->processPage($this->cache,$crawl,$items);
	}

	/**
	 * AnywhoSpider::processPage()
	 * * Processes the page results *
	 * @return bool
	 */
	public function processPage($page,&$items){
		$page=$this->html2txt($page);
		preg_match_all(PATTERN_PAGE_NEXT,$page,$next_page,PREG_PATTERN_ORDER);
		preg_match_all(PATTERN_RESULT_ITEM,$page,$results,PREG_PATTERN_ORDER);
		if(count($results[1])>0){
			for($i=0;$i<=count($results[1])-1;$i++){
				preg_match_all(PATTERN_NAME,$results[1][$i],$name,PREG_PATTERN_ORDER);
				preg_match_all(PATTERN_ADDRESS,$results[1][$i],$address,PREG_PATTERN_ORDER);
				preg_match_all(PATTERN_PHONE,$results[1][$i],$phone,PREG_PATTERN_ORDER);
				$hash=$name[1][0].$phone[1][0].$address[1][0];
				if(!in_array($hash,$items["hash"])){
					array_push($items["data"],array("name"=>$name[1][0],"phone"=>$phone[1][0],"addr"=>str_replace("LINEBREAK","<br>",$address[1][0])));
					array_push($items["hash"],$hash);
				}
			}
		}else{
			//No results found
			return false;
		}
		if(count($next_page[1])>0){
			$url="http://whitepages.anywho.com".(html_entity_decode($next_page[1][0]));
			$this->_crawl($url,$items);
		}else{
			if(count($items["data"])>0){
				$this->any_insert($items);
			}else{
				//No results found
				return false;
			}
		}
	}

	/**
	 * AnywhoSpider::any_insert()
	 * * Inserts gathered information into the database *
	 * @return bool
	 */
	public function any_insert($info){
		if(count($info["data"])>0){
			for($i=0;$i<=count($info["data"])-1;$i++){
				$to_add=true;
				if(CHECK_IF_EXISTS==true){
					$query="SELECT `first_run` FROM `spider` WHERE `name`='".$info["data"][$i]["name"]."' AND `phone`='".$info["data"][$i]["phone"]."' AND `addr`='".$info["data"][$i]["addr"]."' LIMIT 1";
					if($this->db_conn->query($query) and $this->db_conn->affected_rows>0){
						$to_add=false;
					}
				}

				if($to_add==true){
					$items[]="('".join("','",$info["data"][$i])."')";
				}
			}
			$values_str=join(",",$items);

			$query="INSERT INTO `first_run` (`name`,`phone`,`addr`) VALUES".$values_str;
			if($this->db_conn->query($query)){
				return true;
			}else{
				return false;
			}
		}
	}

	/**
	 * AnywhoSpider::initialize()
	 * * Initializes the task *
	 * @return NULL
	 */
	public function initialize($last,$state,$first=null,$street=null,$city=null,$zip=null){
		$url="http://whitepages.anywho.com/results.php?ReportType=34";
		$url.="&qn=".$last;//."&qs=".$state;
		$url=($state!=null) ? $url."&qs=".$state : $url;
		$url=($first!=null) ? $url."&qf=".$first : $url;
		$url=($street!=null) ? $url."&qst=".$street : $url;
		$url=($city!=null) ? $url."&qc=".$city :  $url;
		$url=($zip!=null) ? $url."&qz=".$zip :$url;
		$this->url=$url;
		$this->start=$url;
		$this->state=$state;
		$this->anyname=$last;
	}

	/**
	 * AnywhoSpider::run()
	 * * Performs necessary functions to run the task *
	 * @return NULL
	 */
	public function run(){
		//$this->initialize();
		//$this->_crawl();
		if($this->share==true){
			parent::share_memory("POST",$this->db_conn);
			parent::share_memroy("POST",CHECK_IF_EXISTS);
			parent::share_memory("POST",$this->limit);
			parent::share_memory("POST",$this->crawled);
		}
	}
}
?>