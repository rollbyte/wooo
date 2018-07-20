<?php
namespace wooo\stdlib\auth;

use wooo\stdlib\dbal\interfaces\DbDriver;
use wooo\core\Hash;
use wooo\stdlib\auth\interfaces\IUser;
use wooo\stdlib\auth\interfaces\IPassport;

class LocalPassport implements IPassport {
	/**
	 * @var DbDriver $db
	 */
	private $db;
	
	public function __construct(DbDriver $db) {
		$this->db = $db;
	}
	
	function authorise(array $credentials): IUser {
		$u = $this->db->get("select * from `user` where login = :login and active = 1", ["login" => $credentials["login"]]);
		if ($u) {
			if (Hash::checkHash($credentials["pwd"], $u->pwd)) {
				return new User((string)$u->uid, $u->login);
			}
		}
		return null;
	}
}