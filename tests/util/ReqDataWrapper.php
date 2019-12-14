<?php
namespace wooo\tests\util;

use wooo\core\IRequestDataWrapper;

class ReqDataWrapper implements IRequestDataWrapper
{
    public $a;
    
    public $b;
    
    public $c;
    
    public $d;
    
    public function __construct(object $data)
    {
        $this->a = $data->a;
        $this->b = $data->b;
        $this->c = $data->c;
        $this->d = $data->d;
    }
}