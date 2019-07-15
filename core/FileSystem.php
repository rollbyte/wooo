<?php

namespace wooo\core;

class FileSystem
{
  
    public static function forceDir($dir)
    {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }
  
    public static function deleteDir($dir)
    {
        if (is_dir($dir)) {
            $entries = scandir($dir);
            foreach ($entries as $entry) {
                if ($entry != '.' && $entry != '..') {
                    if (is_dir($dir . DIRECTORY_SEPARATOR . $entry)) {
                        self::DeleteDir($dir . DIRECTORY_SEPARATOR . $entry);
                    } else {
                        unlink($dir . DIRECTORY_SEPARATOR . $entry);
                    }
                }
            }
            rmdir($dir);
        }
    }
  
    public static function listFiles($dir)
    {
        $temp = array_values(array_filter(
            scandir($dir),
            function ($v) {
                return $v != '.' && $v != '..';
            }
        ));
        array_walk(
            $temp,
            function (&$v, $k, $d) {
                $v = pathinfo($d . DIRECTORY_SEPARATOR . $v);
            },
            $dir
        );
        return $temp;
    }
  
    public static function isAbsolute($path)
    {
        if (preg_match('/^([\\/]|[a-zA-Z]\:\\\\).*$/', trim($path))) {
            return true;
        }
        return is_string(parse_url($path, PHP_URL_SCHEME));
    }
  
    public static function path(array $parts)
    {
        return join(DIRECTORY_SEPARATOR, $parts);
    }
}
