<?php
namespace wooo\core\exceptions;

use Psr\Container\NotFoundExceptionInterface;

class ComponentNotFoundException extends ScopeException implements NotFoundExceptionInterface
{
    public function __construct(array $params = [])
    {
        parent::__construct(ScopeException::COMPONENT_NOT_FOUND, $params);
    }
}
