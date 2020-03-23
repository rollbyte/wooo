<?php

namespace wooo\core\events\app;

use wooo\core\App;

class AppUseEvent extends AppEvent
{
    protected function __construct(App $app, string $route, $module)
    {
        parent::__construct(AppEvent::USE, $app, ['route' => $route, 'module' => $module]);
    }
    
    public function module()
    {
        return $this->data['module'];
    }
    
    public function route(): string
    {
        return $this->data['route'];
    }
}
