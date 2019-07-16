<?php
namespace wooo\tests\util;

use wooo\lib\auth\interfaces\IUser;
use wooo\lib\auth\interfaces\IPassport;
use wooo\lib\auth\User;

class VirtualPassport implements IPassport
{
    public function authorise(array $credentials): ?IUser
    {
        return new User(1, $credentials["login"]);
    }
}
