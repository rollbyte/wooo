<?php
namespace wooo\stdlib\dbal;

class DbException extends \Exception
{
    const SEQUENCE_FAILED = 9001;
    const EXEC_FAILED = 9002;
    const FETCH_FAILED = 9003;
    
    private static $messages = [
        self::SEQUENCE_FAILED => 'Failed to get next value from sequence %s',
        self::EXEC_FAILED => 'Data operation failed for query "%s" with specified parameters %s',
        self::FETCH_FAILED => 'Data request failed for query "%s" with specified parameters %s'
    ];
    
    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(
            is_int($code) ? vsprintf(self::$messages[$code] ?? 'Unknown database error', $params) : $code,
            is_int($code) ? $code : 9000,
            $cause
        );
    }
}
