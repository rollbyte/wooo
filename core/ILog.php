<?php

namespace wooo\core;

interface ILog
{
  
    public function error(\Throwable $error);
  
    public function warn($msg);
  
    public function info($msg);
}
