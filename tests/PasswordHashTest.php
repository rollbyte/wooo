<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\PasswordHash;

class PasswordHashTest extends TestCase
{
    public function testHash(): void
    {
        $ph = new PasswordHash();
        $hash = $ph->apply('12345');
        $this->assertTrue($ph->check('12345', $hash), 'password hash test failed');
    }
}
