<?php
namespace wooo\stdlib\auth;

use wooo\stdlib\dbal\interfaces\DbDriver;
use wooo\stdlib\auth\interfaces\IUser;
use wooo\stdlib\auth\interfaces\IPassport;
use wooo\core\Session;

abstract class OAuth2Passport implements IPassport {
	/**
	 * @var DbDriver $db
	 */
	private $db;
	
	protected $clientId;
	
	protected $authUrl;
	
	protected $accessUrl;
	
	protected $scope;
	
	protected $secret;
	
	protected $callBackUrl;
	
	protected $authParams = [
		'cid' => 'client_id',
		'secret' => 'client_secret',
		'state' => 'state',
		'redirect' => 'redirect_uri',
		'scope' => 'scope',
		'error' => 'error',
		'errorMsg' => 'error_description',
		'code' => 'code'
	];
	
	protected $profileMap = [
		'email' => 'email',
		'login' => 'name'
	];
	
	public function __construct(DbDriver $db, string $clientId, string $secret, string $callbackUrl) {
		$this->db = $db;
		$this->clientId = $clientId;
		$this->secret = $secret;
		$this->callBackUrl = $callbackUrl;
	}
	
	protected function checkState($state) {
		return $state == Session::instance()->get('oauth2state');
	}
	
	protected function addAccessParams(array $params): array {
		return $params;
	}
	
	protected function parseAccess(string $access): array {
		return [];
	}
	
	protected function getAccess(string $code): array {
		return [];
	}
	
	public function authorise(array $credentials): IUser {
		if ($credentials[$this->authParams['error']]) {
			throw new \Exception('OAuth2: '.$credentials[$this->authParams['errorMsg']]);
		}
		
		$state = $credentials[$this->authParams['state']];
		if (!$this->checkState($state)) {
			throw new \Exception('OAuth state invalid!');
		}
		
		$code = $credentials[$this->authParams['code']];		
		
		if ($this->accessUrl) {
			$q = [];
			$q[$this->authParams['cid']] = $this->clientId;
			$q[$this->authParams['secret']] = $this->secret;
			$q[$this->authParams['code']] = $code;
			$access = $this->parseAccess(file_get_contents($this->accessUrl.'?'.http_build_query($this->addAccessParams($q))));
		} else {
			$access = $this->getAccess($code);
		}
		$profile = [];
		foreach ($access as $nm => $v) {
			$profile[isset($this->profileMap[$nm]) ? $this->profileMap[$nm] : $nm] = $v;
		}
		
		if (!isset($profile['email']) || !$profile['email'] || !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
			throw new \Exception('User email was not obtained from external application!');
		}
		
		$u = $this->db->get("select * from `user` where email = :email", ["email" => $profile["email"]]);
		if ($u) {
			if (!$u->active) {
				$this->db->execute("update `user` set active = 1 where uid = :id", ["uid" => $u->uid]);
			}
		} else {
			$data = [];
			$data['email'] = $profile['email'];
			$data['login'] = $profile['login'] ? $profile['login'] : $profile['email'];
			$data['active'] = 1;
			$uid = $this->db->execute("insert `user` (email, login, active) values (:email, :login, 1)", $data);
			$u = $this->db->get("select * from `user` where uid = :id", ["id" => $uid]);
		}
		return new User((string)$u->uid, $u->login, $profile);
	}
	
	protected function addAuthParams(array $params): array {
		return $params;
	}
	
	public function authURL() {
		$state = bin2hex(random_bytes(10));
		Session::instance()->set('oauth2state', $state);
		$q = [];
		$q[$this->authParams['cid']] = $this->clientId;
		$q[$this->authParams['state']] = $state;
		$q[$this->authParams['scope']] = $this->scope;
		$q[$this->authParams['redirect']] = $this->callBackUrl;
		return $this->authUrl.'?'.http_build_query($this->addAuthParams($q));
	}
}