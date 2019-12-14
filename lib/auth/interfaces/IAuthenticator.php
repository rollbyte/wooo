<?php

namespace wooo\lib\auth\interfaces;

interface IAuthenticator
{
  
    public const TYPE_LOCAL = 'local';
  
    public function login(array $credentials, ?string $passport = null): void;
  
    public function passports(): array;
  
    public function logout(): void;
  
    public function user(): ?IUser;
  
    public function force(IUser $user): void;
}
