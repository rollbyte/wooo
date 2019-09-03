<?php

namespace wooo\lib\auth\interfaces;

interface IUser
{
  
    public function id(): string;
  
    public function login(): string;
  
    public function name(): string;
  
    public function properties(): array;
    
    public function set(string $nm, $value);
    
    public function get(string $nm);
}
