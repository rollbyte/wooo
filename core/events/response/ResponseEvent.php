<?php

namespace wooo\core\events\response;

use wooo\core\events\Event;
use wooo\core\Response;

class ResponseEvent extends Event
{
    public const RENDER = 'response::render';
    
    public const SEND = 'response::send';

    public const REDIRECT = 'response::redirect';
    
    protected function __construct(string $code, Response $response, array $data = [])
    {
        if (!in_array($code, [self::RENDER, self::SEND, self::REDIRECT])) {
            throw new \ErrorException("Invalid response event name $code");
        }
        parent::__construct($code, $response, $data);
    }
}
