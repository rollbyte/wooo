<?php
namespace wooo\tests\util;

use wooo\lib\auth\interfaces\IUser;
use wooo\lib\auth\interfaces\IPassport;
use wooo\lib\auth\User;

class VirtualPassport implements IPassport
{
    public function authenticate(array $credentials): ?IUser
    {
        return new User(1, $credentials["login"]);
    }
    
    public function applicable(array $credentials): bool
    {
        return isset($credentials['login']);   
    }
}
