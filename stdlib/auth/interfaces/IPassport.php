<?php

namespace wooo\stdlib\auth\interfaces;

interface IPassport
{
  
    public function authorise(array $credentials): ?IUser;
}
