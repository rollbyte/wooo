<?php
namespace wooo\core\exceptions;

class CoreException extends \Exception
{
    const INVALID_HASH_ALGO = 1000;
    const INVALID_IMAGE = 2000;
    const INVALID_IMAGE_TYPE = 2010;
    const INVALID_HANDLER_ARGUMENT = 2100;
    const NOT_PIPE_UNIT = 2200;
    
    const PATH_NOT_ABSOLUTE = 3001;
    
    const IO_OPERATION_FAILED = 5000;
    
    private static $messages = [
        self::INVALID_HASH_ALGO => 'Invalid hashing algorythm.',
        self::INVALID_IMAGE => 'Image is invalid.',
        self::INVALID_IMAGE_TYPE => 'Invalid result type specified for image conversion.',
        self::PATH_NOT_ABSOLUTE => 'Path should be absolute.',
        self::IO_OPERATION_FAILED => 'Failed to perform %s operation.',
        self::INVALID_HANDLER_ARGUMENT => 'Invalid handler argument type specified.',
        self::NOT_PIPE_UNIT => 'Piping is not applicable to the stream of %s class.'
    ];

    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(vsprintf(self::$messages[$code] ?? 'Unknown error', $params), $code, $cause);
    }
}
