<?php
namespace wooo\core;

class FileSystem {
	public static function forceDir($dir){
		if (!file_exists($dir)){
			mkdir($dir,0777,true);
		}
	}
	
	public static function deleteDir($dir){
		if (is_dir($dir)){
			$entries = scandir($dir);
			foreach ($entries as $entry)
				if ($entry != '.' && $entry != '..'){
					if (is_dir($dir.'/'.$entry))
						self::DeleteDir($dir.'/'.$entry);
					else unlink($dir.'/'.$entry);
			}
			rmdir($dir);
		}
	}
	
	public static function listFiles($dir){
		$temp = array_filter(scandir($dir),create_function('$v', 'return $v != "." && $v != "..";'));
		array_walk($temp,create_function('&$v, $k, $d','$v = pathinfo($d."/".$v);'),$dir);
		return $temp;
	}
	
	public static function isAbsolute($path){
		if (preg_match('/^([\\\\\\/]|[a-zA-Z]\:|https?\:).*$/', trim($path)))
			return true;
		return parse_url($path, PHP_URL_SCHEME);
	}
	
	public static function path(array $parts) {
		return join(DIRECTORY_SEPARATOR, $parts);
	}
}