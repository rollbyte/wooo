<?php

spl_autoload_register(function ($class) {
	$classPath = str_ireplace('\\', DIRECTORY_SEPARATOR, $class);
	if ($inc = stream_resolve_include_path($classPath.'.inc')) {
		include_once $inc;
	} else if ($php = stream_resolve_include_path($classPath.'.php')) {
		include_once $php;
	}
}, true, true);	

require "wooo/core/App.inc";