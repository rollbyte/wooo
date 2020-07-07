<?php

namespace wooo\lib\middleware;

use Exception;
use wooo\core\Response;

class ClickJack
{
  public static function handler($setting = null)
  {
    return function (Response $resp) use ($setting) {
      if ($setting && !in_array(strtoupper($setting), ['DENY', 'SAMEORIGIN'])) {
        throw new Exception('Invalid value specified for X-Frame-Options header');
      }
      $resp->setHeader('X-Frame-Options', $setting ? strtoupper($setting) : 'DENY');
    };
  }
}