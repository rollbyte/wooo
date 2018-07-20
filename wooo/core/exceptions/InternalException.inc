<?php
namespace wooo\core\exceptions;

class InternalException extends \Exception {
	public function __construct($msg = null, \Throwable $prev = null) {
		parent::__construct($msg ? $msg : 'Internal server error.', 500, $prev);
	}
}