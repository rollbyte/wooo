<?php

namespace wooo\core\exceptions;

class NotFoundException extends \Exception
{
    public function __construct($msg = null, \Throwable $prev = null)
    {
        parent::__construct($msg ? $msg : 'Resource not found.', 404, $prev);
    }
}
