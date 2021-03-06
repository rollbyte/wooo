<?php

namespace wooo\lib\auth;

use wooo\lib\auth\interfaces as wooo;

class User implements wooo\IUser
{
  
    private $id;
  
    private $login;
  
    private $name;
  
    private $properties = [];
  
    public function __construct(string $id, string $login = null, array $properties = null, string $name = null)
    {
        $this->id = $id;
        $this->login = $login ? $login : $this->id;
        $this->name = $name ? $name : $this->login;
        if (is_array($properties)) {
            $this->properties = $properties;
        }
    }
  
    public function id(): string
    {
        return $this->id;
    }
  
    public function login(): string
    {
        return $this->login;
    }
  
    public function name(): string
    {
        return $this->name;
    }
  
    public function properties(): array
    {
        return $this->properties;
    }
    
    public function set($nm, $value)
    {
        $this->properties[$nm] = $value;
    }
    
    public function get($nm)
    {
        return isset($this->properties[$nm]) ? $this->properties[$nm] : null;
    }
}
