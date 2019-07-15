<?php
namespace wooo\core\exceptions;

class AccessDeniedException extends \Exception
{
    public function __construct($msg = null, \Throwable $prev = null)
    {
        parent::__construct($msg ? $msg : 'Access denied.', 403, $prev);
    }
}
