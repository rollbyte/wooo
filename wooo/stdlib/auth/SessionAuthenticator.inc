<?php
namespace wooo\stdlib\auth;

use wooo\core as core;
use wooo\stdlib\auth\interfaces as auth;
use wooo\stdlib\auth\interfaces\IUser;

class SessionAuthenticator implements auth\IAuthenticator {
	private $passportTypes = [];
	
	private $passports = [];
	
	public function setPassport(auth\IPassport $passport) {
		array_push($this->passports, $passport);
	}
	
	public function setPassportType(string $type) {
		array_push($this->passportTypes, $type);
	}
	
	protected function passportMap() {
		$combine = [];
		foreach ($this->passports as $ind => $p) {
			if (isset($this->passportTypes[$ind])) {
				$combine[$this->passportTypes[$ind]] = $p;
			} else {
				$combine[auth\IAuthenticator::TYPE_LOCAL] = $p;
			}
		}
		return $combine;
	}
	
	public function login(array $credentials, string $type = auth\IAuthenticator::TYPE_LOCAL): void {
		$combine = $this->passportMap();
		
		if (isset($combine[$type])) {
			/**
			 * @var auth\IPassport
			 */
			$p = $combine[$type];
			
			$u = $p->authorise($credentials);
			if ($u) {
				core\Session::instance()->set("curr_user", $u);
				return;
			}
			throw new \Exception('Invalid user credentials!');
		}
		throw new \Exception('No passport associated to type "' + $type + '"!');
	}
	
	function force(IUser $user): void {
		core\Session::instance()->set("curr_user", $user);
	}
	
	public function logout(): void {
		core\Session::instance()->set("curr_user", null);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \wooo\stdlib\auth\interfaces\IAuthenticator::user()
	 */
	public function user(): ?IUser {
		return core\Session::instance()->get("curr_user");
	}
	
	public function passports(): array {
		return $this->passportMap();
	}
}