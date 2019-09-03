<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Token;

class TokenTest extends TestCase
{
    public function testHashValidAlgo(): void
    {
        $t = new Token(24);
        $t2 = new Token($t->masked());
        $this->assertEquals($t->value(), $t2->value());
    }
}