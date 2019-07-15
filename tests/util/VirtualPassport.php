<?php
namespace wooo\tests\util;

use wooo\stdlib\auth\interfaces\IUser;
use wooo\stdlib\auth\interfaces\IPassport;
use wooo\stdlib\auth\User;

class VirtualPassport implements IPassport
{
    public function authorise(array $credentials): ?IUser
    {
        return new User(1, $credentials["login"]);
    }
}
