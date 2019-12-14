<?php

namespace wooo\core\exceptions;

use Psr\Container\ContainerExceptionInterface;

class ScopeException extends \Exception implements ContainerExceptionInterface
{
    public const CIRCULAR_DEPENDENCY = 4001;
    public const INJECTION_FAILED = 4002;
    public const COMPONENT_FAILED = 4003;
    public const COMPONENT_NOT_FOUND = 4004;
    
    private static $messages = [
        self::CIRCULAR_DEPENDENCY => 'Circular dependency injection in constructor.',
        self::INJECTION_FAILED => 'Failed to read DI configuration for %s option.',
        self::COMPONENT_FAILED => 'Failed to read DI configuration for component %s.',
        self::COMPONENT_NOT_FOUND => 'Component for identifier %s not found in container'
    ];
    
    public function __construct($code, $params = [], \Throwable $cause = null)
    {
        parent::__construct(vsprintf(self::$messages[$code] ?? 'Unknown error', $params), $code, $cause);
    }
}
