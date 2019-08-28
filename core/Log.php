<?php

namespace wooo\core;

class Log implements ILog
{
  
    public function warn(string $msg): void
    {
        $date = date("Y-m-d h:m:s");
        $de = ini_get('display_errors');
        if ($de) {
            echo "[$date] [WARN] $msg" . PHP_EOL;
        } else {
            error_log("[$date] [WARN] $msg");
        }
    }
  
    public function error(\Throwable $error): void
    {
        $date = date("Y-m-d h:m:s");
        $file = $error->getFile();
        $line = $error->getLine();
        $msg = $error->getMessage();
        $de = ini_get('display_errors');
        if ($de) {
            echo "<pre style=\"white-space: pre-wrap;\">[$date] [ERROR] [$file :$line] $msg" . PHP_EOL . PHP_EOL;
            echo $error->getTraceAsString() . '</pre>';
        } else {
            error_log("[$date] [ERROR] [$file :$line] $msg");
            error_log($error->getTraceAsString());
        }
    }
  
    public function info(string $msg): void
    {
        $date = date("Y-m-d h:m:s");
        $de = ini_get('display_errors');
        if ($de) {
            echo "[$date] [INFO] $msg" . PHP_EOL;
        } else {
            error_log("[$date] [INFO] $msg");
        }
    }
}
