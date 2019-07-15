<?php
namespace wooo\tests\util;

use wooo\core\App;

class ComponentMock2
{
    private $app = null;
    
    public function __construct(App $app)
    {
        $this->app = $app;
    }
    
    public function getContext(): string
    {
        return $this->app->appPath();
    }    
}