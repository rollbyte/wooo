<?php
namespace wooo\core;

final class Response {
	
	private static $instance;
	
	public static function instance($config = null) {
		if (!self::$instance) {
			self::$instance = new Response($config); 
		}
		return self::$instance;
	}
	
	private function __construct(Config $config) {
	}
	
	public function redirect($url) {
		$host = parse_url($url, PHP_URL_HOST);
		if ($host) {
			header("Location: $url", true, 302);
			exit;
		}
		if (!$url || $url[0] != '/') {
			$url = '/'.$url;
		}
		$appBase = App::appBase();
		header("Location: $appBase$url", true, 302);
		exit;		
	}
	
	public function render($path, $data) {
		extract($data, EXTR_OVERWRITE);
		include $path;
		exit;
	}
	
	public function send($data) {
		if (is_string($data)) {
			echo $data;
		}
		if (is_array($data) || is_object($data)) {
			$this->setHeader("Content-Type:application/json; charset=utf-8");
			echo json_encode($data);
		}
		exit;
	}
	
	public function setStatus($status) {
		http_response_code($status);
		return $this;
	}
	
	public function setCookie($name,$value,$expire = null, $path = null, $http_only = false) {
		if (is_null($value)) {
			$expire = time()-86400;
		}				
		
		$oldval = Request::instance()->getCookie($name);
		if (is_array($oldval) || is_array($value)){
			if (empty($value)) {
				$unset = array_keys($oldval);
			} else if (empty($oldval)) {
					$unset = array();
			} else {
					$unset = array_diff(array_keys($oldval), array_keys($value));
			}
				
			if (is_array($value)) {
				foreach ($value as $key=>$v) {
					if (!in_array($key, $unset)) {
						$this->setCookie($name."[$key]", $v, $expire, $path, $http_only);
					}
				}
			}
			
			foreach ($unset as $u) {
				$this->setCookie($name."[$u]", null, time()-86400, $path);
			}
		} else {
			setcookie($name,$value,$expire,$path,null,null,$http_only);
		}
		return $this;
	}
	
	public function setHeader($header) {
		header($header);
		return $this;
	}
}