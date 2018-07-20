<?php
namespace wooo\core;

interface ILog {
	function error(\Throwable $error);
	
	function warn($msg);
	
	function info($msg);
}