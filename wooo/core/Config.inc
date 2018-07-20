<?php
namespace wooo\core;

class Config {
	private $config;

	public function __construct($config) {
		$this->config = $config;
	}

	public function get($name, $default = null) {
		return isset($this->config[$name]) ? $this->config[$name] : $default; 
	}
	
	public function set($name, $value) {
		$this->config[$name] = $value;
	}
}