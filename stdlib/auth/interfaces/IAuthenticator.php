<?php

namespace wooo\stdlib\auth\interfaces;

interface IAuthenticator
{
  
    const TYPE_LOCAL = 'local';
  
    public function login(array $credentials, string $type = 'local'): void;
  
    public function passports(): array;
  
    public function logout(): void;
  
    public function user(): ?IUser;
  
    public function force(IUser $user): void;
}
