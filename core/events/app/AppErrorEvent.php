<?php

namespace wooo\core\events\app;

use wooo\core\App;

class AppErrorEvent extends AppEvent
{
    protected function __construct(App $app, \Throwable $e)
    {
        parent::__construct(AppEvent::USE, $app, ['error' => $e]);
    }
    
    public function error(): \Throwable
    {
        return $this->data['error'];
    }    
}