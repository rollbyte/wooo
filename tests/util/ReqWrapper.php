<?php
namespace wooo\tests\util;

use wooo\core\IRequestWrapper;
use wooo\core\Request;

class ReqWrapper implements IRequestWrapper
{
    public $a;
    
    public $b;
    
    public $c;
    
    public $d;
    
    public function __construct(Request $req)
    {
        $this->a = $req->a;
        $this->b = $req->b;
        $this->c = $req->c;
        $this->d = $req->d;
    }
}