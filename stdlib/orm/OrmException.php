<?php
namespace wooo\stdlib\orm;

class OrmException extends \Exception
{
    const CREATE_FAILED = 10001;
    const SAVE_FAILED = 10002;
    const UPDATE_FAILED = 10003;
    const DELETE_FAILED = 10004;
    const FETCH_FAILED = 10005;
    const ITERATE_FAILED = 10006;
    
    const CLASS_NO_KEY = 10007;
    const ATTR_NOT_FOUND = 10008;
    const QUERY_SYTAX_ERROR = 10009;
    const INVALID_OPER = 10010;
    const NO_DATA_FOR_REF_FIELD = 10011;
    const NO_KEY_DATA = 10012;
    const INVALID_ID = 10013;
    const INVALID_ATTRIBUTE_VALUE = 10014;
    
    private static $messages = [
        self::CREATE_FAILED => '%s class instance creation failed.',
        self::SAVE_FAILED => '%s class instance saving failed.',
        self::UPDATE_FAILED => '%s class instance update failed.',
        self::DELETE_FAILED => '%s class instance delete failed.',
        self::FETCH_FAILED => '%s class fetch failed.',
        self::ITERATE_FAILED => '%s class iteration failed.',
        self::CLASS_NO_KEY => 'Key not specified for class "%s".',
        self::ATTR_NOT_FOUND => 'Attribute "%s" not found in class "%s".',
        self::QUERY_SYTAX_ERROR => 'Query syntax error.',
        self::INVALID_OPER => 'Unknown operation "%s" specified in expression.',
        self::NO_DATA_FOR_REF_FIELD => 'No data specified for reference field "%s".',
        self::NO_KEY_DATA => 'Key data was not provided for class "%s".',
        self::INVALID_ID => 'Invalid id "%s" specified for class "%s"',
        self::INVALID_ATTRIBUTE_VALUE => 'Invalid value specified for attribute "%s"'
    ];
    
    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(
            is_int($code) ? vsprintf(self::$messages[$code] ?? 'Unknown ORM error', $params) : $code,
            is_int($code) ? $code : 10000,
            $cause
        );
    }
}
