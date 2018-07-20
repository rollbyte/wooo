<?php
namespace wooo\stdlib\auth\interfaces;

interface IPassport {
	function authorise(array $credentials): IUser;
}