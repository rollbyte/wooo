<?php
namespace wooo\stdlib\auth\interfaces;

interface IUser {
	function id(): string;
	
	function login(): string;
	
	function name(): string;
	
	function properties(): array;
}