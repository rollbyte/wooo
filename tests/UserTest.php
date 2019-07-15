<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\stdlib\auth\User;

class UserTest extends TestCase
{   
    public function testProfile(): void
    {
        $u = new User(1, 'user', ['prop1' => 234], 'John Doe');
        $this->assertEquals(1, $u->id(), 'user id test failed');
        $this->assertEquals('user', $u->login(), 'user login test failed');
        $this->assertEquals('John Doe', $u->name(), 'user name test failed');
        $this->assertEquals(234, $u->get('prop1'), 'user property getting test failed');
        $u->set('prop2', 345);
        $this->assertEquals(345, $u->get('prop2'), 'user property setting test failed');
    }
}
