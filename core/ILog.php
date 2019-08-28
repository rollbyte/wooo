<?php

namespace wooo\core;

interface ILog
{
  
    public function error(\Throwable $error): void;
  
    public function warn(string $msg): void;
  
    public function info(string $msg): void;
}
