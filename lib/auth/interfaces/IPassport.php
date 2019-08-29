<?php

namespace wooo\lib\auth\interfaces;

interface IPassport
{
    /**
     * tries to obtain a user account according to the specified credentials
     * returns authenticated user account on success
     * @param array $credentials
     * @return IUser|NULL
     */
    public function authenticate(array $credentials): ?IUser;
    
    /**
     * checks if this passport is applicable to the specified credentials
     * @param array $credentials
     * @return bool
     */
    public function applicable(array $credentials): bool;
}
