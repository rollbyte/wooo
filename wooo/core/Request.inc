<?php
namespace wooo\core;
require "UploadedFile.inc";

final class Request {
	
	private static $instance;
	
	private $IsHttps = false;
	
	/**
	 * @var boolean
	 */
	private $IsXmlHttpRequest = false;
	
	private $JsonObject = null;
	
	private $uri;
	
	private $path;
	
	private $queryParameters = [];
	private $bodyParameters = [];
	private $fileParameters = [];
	private $pathParameters = [];
	private $rawPostData = null;
	
	private $tz;
	
	private $locale;
	
	public static function instance(Config $config = null) {
		if (!self::$instance) {
			self::$instance = new Request($config); 
		}
		return self::$instance;
	}
	
	private function acceptValue($value, $urldecode = false) {
		if (get_magic_quotes_gpc()){
			if (is_array($value))
				array_walk_recursive(
						$value,
						function (&$item,$key,$urldecode) {
							$item = stripslashes($urldecode ? rawurldecode($item) : $item);
						},
						$urldecode
				);
			else
				$value = stripslashes($urldecode ? rawurldecode($value) : $value);
		}
		if (!is_null($value))
			return $value;
		return null;
	}
	
	private function acceptParams($params, $urldecode, &$member) {
		foreach ($params as $key=>$value) {
			$v = $this->acceptValue($value,$urldecode);
			if (is_array($member))
				$member[$key] = $v;
		}
	}	
	
	private function __construct(Config $config = null) {
		if (!isset($_SERVER['REQUEST_URI'])){
			if (isset($_SERVER['HTTP_X_ORIGINAL_URL']))
				$this->uri = $_SERVER['HTTP_X_ORIGINAL_URL'];
				// IIS Isapi_Rewrite
			else if (isset($_SERVER['HTTP_X_REWRITE_URL']))
				$this->uri = $_SERVER['HTTP_X_REWRITE_URL'];
			else {
				// Use ORIG_PATH_INFO if there is no PATH_INFO
				if (!isset($_SERVER['PATH_INFO']) && isset($_SERVER['ORIG_PATH_INFO']) )
					$_SERVER['PATH_INFO'] = $_SERVER['ORIG_PATH_INFO'];
		
				// Some IIS + PHP configurations puts the script-name in the path-info (No need to append it twice)
				if ( isset($_SERVER['PATH_INFO']) ) {
					if ( $_SERVER['PATH_INFO'] == $_SERVER['SCRIPT_NAME'] )
						$this->uri = $_SERVER['PATH_INFO'];
					else
						$this->uri = $_SERVER['SCRIPT_NAME'] . $_SERVER['PATH_INFO'];
				}
			}
		} else {
			$this->uri = $_SERVER['REQUEST_URI'];
		}
		$this->path = str_ireplace(App::appRoot(), '', $this->uri);
		if (!$this->path || $this->path[0] != '/') {
			$this->path = '/'.$this->path;
		}
		
		$this->IsXmlHttpRequest = strtolower($this->getHeader("X_REQUESTED_WITH")) == "xmlhttprequest";
		$this->IsHttps = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] != 'off');
			
		if (strtolower($this->getHeader('Content-Type')) == 'application/json') {
			$this->JsonObject = json_decode($this->getRawPostData());
		}
		
		if ($tzh = $config->get('tzHeader')) {
			$tzOffset = $this->getHeader($tzh);
			if ($tzOffset) {
				$tz_name = timezone_name_from_abbr("", $tzOffset, 0);
				if (!$tz_name) {
					$tz_name = timezone_name_from_abbr("", $tzOffset, 1);
				}
				if ($tz_name) {
					date_default_timezone_set($tz_name);
				}
			}
			$this->tz = new \DateTimeZone(date_default_timezone_get());
		}

		if ($h = $this->getHeader('Accept-Language')) {
			$this->locale = new Locale(\locale_accept_from_http($h));
		} else {
			$this->locale = new Locale(\locale_get_default());
		}
		
		$this->acceptParams($_GET, true, $this->queryParameters);
		$this->acceptParams($_POST, false, $this->bodyParameters);
				
		foreach ($_FILES as $key=>$file){
			if (is_array($file)/* && (($file['error'] == 0) || is_array($file['error']))*/){
				if (is_array($file["name"])){
					$files = array();
					foreach ($file["name"] as $i => $fname){
						if ($file["error"][$i] == UPLOAD_ERR_OK)
							$files[$i] = new UploadedFile(array(
										"name"=>$fname,
										"tmp_name"=>$file["tmp_name"][$i],
										"type"=>$file["type"][$i],
										"size"=>$file["size"][$i],
										"error"=>UPLOAD_ERR_OK
								));
						else
							$files[$i] = new UploadedFile(array("error"=>$file["error"][$i]));
					}
					$this->fileParameters[$key] = $files;
				} else {
					$this->fileParameters[$key] = new UploadedFile($file);
				}
			}
		}
	}
	
	public function getRawPostData(){
		if (is_null($this->rawPostData))
			$this->rawPostData = file_get_contents("php://input");
		return $this->rawPostData;
	}
	
	public function getHeader($nm){
		$nm = strtoupper(str_replace('-', '_', $nm));
		if (isset($_SERVER[$nm]))
			return $_SERVER[$nm];
		else if (isset($_SERVER['HTTP_'.$nm]))
			return $_SERVER['HTTP_'.$nm];
		return null;
	}
	
	public function getCookie($nm){
		if (isset($_COOKIE[$nm])){
			$v = $this->acceptValue($_COOKIE[$nm]);
			if ($v) return $v;
		}
		return null;
	}
	
	public function checkPath($pattern) {
		$re = preg_replace('/\\\\:[a-z0-9_]+/i', '([a-z0-9_]+)', preg_quote($pattern, '/'));
		return preg_match('/^'.$re.'$/i', $this->path) ? true : false;
	}
	
	public function parsePath($pattern) {
		$re = preg_replace('/\\\\:[a-z0-9_]+/i', '([a-z0-9_]+)', preg_quote($pattern, '/'));
		$pvals = [];
		$matched = preg_match('/^'.$re.'$/i', $this->path, $pvals);
		if ($matched) {
			$pnames = [];
			preg_match_all('/:([a-z0-9_]+)/i', $pattern, $pnames, PREG_SET_ORDER);
			$this->pathParameters = [];
			for ($i = 0; $i < count($pnames); $i++) {
				$pn = $pnames[$i][1];
				$this->pathParameters[$pn] = $pvals[$i + 1];
			}
		}
	}
	
	public function getLocale() {
		return $this->locale;
	}
	
	public function getTimeZone() {
		return $this->tz;
	}
	
	public function isAjax() {
		return $this->IsXmlHttpRequest;
	}
	
	public function isSecured() {
		return $this->IsHttps;
	}
	
	public function getHost() {
		return $_SERVER['HTTP_HOST'];
	}
	
	public function getMethod() {
		return $_SERVER['REQUEST_METHOD'];
	}
	
	public function getUri() {
		return $this->uri;
	}
	
	public function getPath() {
		return $this->path;
	}
	
	public function getParameters() {
		return $this->pathParameters;
	}
	
	public function getQuery() {
		return $this->queryParameters;
	}
	
	public function getBody() {
		return $this->bodyParameters;
	}
	
	public function getJson() {
		return $this->JsonObject;
	}
	
	public function getFiles() {
		return $this->fileParameters;
	}
}