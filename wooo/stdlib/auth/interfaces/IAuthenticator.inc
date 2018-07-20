<?php
namespace wooo\stdlib\auth\interfaces;

interface IAuthenticator {
	const TYPE_LOCAL = 'local';
	
	function login(array $credentials, string $type = 'local'): void;
	
	function passports(): array;
	
	function logout(): void;
	
	function user(): ?IUser;
	
	function force(IUser $user): void;
}