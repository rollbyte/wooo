<?php
namespace wooo\tests\util;

use wooo\core\App;

class ComponentMock
{
    private $value = '';
    
    private $dependency1 = null;
    
    private $dependency2 = null;
    
    private $prefix = null;
    
    private $app = null;
    
    public function __construct(string $value, App $app, ?ComponentMock $dependency = null)
    {
        $this->value = $value;
        $this->dependency1 = $dependency;
        $this->app = $app;
    }
    
    public function setDependency2(ComponentMock $dep): void
    {
        $this->dependency2 = $dep;
    }
    
    public function setPrefix($pref): void
    {
        $this->prefix = $pref;
    }
    
    public function getContext(): string
    {
        return $this->app->appPath();
    }
    
    public function getValue(): string
    {
        $result = ($this->prefix ? $this->prefix . '.' : '') . $this->value;
        if ($this->dependency1) {
            $result .= ':'.$this->dependency1->getValue();
        }
        if ($this->dependency2) {
            $result .= ':'.$this->dependency2->getValue();
        }
        return $result;
    }
}