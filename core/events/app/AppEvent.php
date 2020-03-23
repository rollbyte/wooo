<?php

namespace wooo\core\events\app;

use wooo\core\events\Event;
use wooo\core\App;

class AppEvent extends Event
{
    public const USE = 'app::use';
    
    public const EXIT = 'app::exit';
    
    public const ERROR = 'app::error';
    
    protected function __construct(string $code, App $app, array $data = [])
    {
        if (!in_array($code, [self::USE, self::EXIT, self::ERROR])) {
            throw new \ErrorException("Invalid application event name $code");
        }
        parent::__construct($code, $app, $data);
    }
}
