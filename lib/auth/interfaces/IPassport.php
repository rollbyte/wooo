<?php

namespace wooo\lib\auth\interfaces;

interface IPassport
{
  
    public function authorise(array $credentials): ?IUser;
}
