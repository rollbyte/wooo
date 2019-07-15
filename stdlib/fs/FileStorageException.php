<?php
namespace wooo\stdlib\fs;

class FileStorageException extends \Exception
{
    const LOCAL_NO_APP_PATH = 7001;
    
    private static $messages = [
        self::LOCAL_NO_APP_PATH => 'File storage failed to determine application path.'
    ];
    
    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(
            is_int($code) ? vsprintf(self::$messages[$code] ?? 'Unknown authentication error', $params) : $code,
            is_int($code) ? $code : null,
            $cause
        );
    }
}
