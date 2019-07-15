<?php
namespace wooo\tests\util;

class ComponentMock3
{   
    private $ref = null;
    
    public function __construct(ComponentMock2 $ref)
    {
        $this->ref = $ref;
    }
    
    public function getContext(): string
    {
        return $this->ref->getContext();
    }
}