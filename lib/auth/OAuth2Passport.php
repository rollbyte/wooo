<?php

namespace wooo\lib\auth;

use wooo\lib\dbal\interfaces\DbDriver;
use wooo\lib\auth\interfaces\IUser;
use wooo\lib\auth\interfaces\IPassport;
use wooo\core\Session;
use wooo\core\Request;
use wooo\core\Response;

abstract class OAuth2Passport implements IPassport
{
  
    /**
     *
     * @var DbDriver $db
     */
    private $db;
  
    protected $clientId;
  
    protected $authUrl;
  
    protected $accessUrl;
  
    protected $scope;
  
    protected $secret;
  
    protected $callBackUrl;
    
    private $tableName = 'user';
    
    /**
     * @var Session
     */
    protected $session;
  
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
  
    protected $profileMap = [];
  
    public function __construct(Request $req, DbDriver $db, string $clientId, string $secret, string $callbackUrl)
    {
        $this->db = $db;
        $this->clientId = $clientId;
        $this->secret = $secret;
        $this->callBackUrl = $callbackUrl;
        $this->session = $req->session();
    }
    
    public function setTableName($name)
    {
        $this->tableName = $name;
    }
  
    protected function checkState($state)
    {
        return $state == $this->session->get('oauth2state_' . $this->clientId);
    }
  
    protected function addAccessParams(array $params): array
    {
        return $params;
    }
  
    protected function parseAccess(string $access): array
    {
        return [];
    }
  
    protected function getAccess(string $code): array
    {
        return [];
    }
    
    /**
     * {@inheritDoc}
     * @param array $credentials [$this->authParams['code'] => OAuth2 code]
     * @see \wooo\lib\auth\interfaces\IPassport::applicable()
     */
    public function applicable(array $credentials): bool
    {
        return isset($credentials[$this->authParams['code']]);
    }
    
    /**
     * {@inheritDoc}
     * @see \wooo\lib\auth\interfaces\IPassport::authenticate()
     */
    public function authenticate(array $credentials): ?IUser
    {
        if (isset($credentials[$this->authParams['error']])) {
            throw new AuthException('OAuth2: ' . $credentials[$this->authParams['errorMsg']]);
        }
    
        $state = $credentials[$this->authParams['state']] ?? null;
        if (!$this->checkState($state)) {
            throw new AuthException(AuthException::OAUTH_INVALID_STATE);
        }
    
        $code = $credentials[$this->authParams['code']];
    
        if ($this->accessUrl) {
            $q = [];
            $q[$this->authParams['cid']] = $this->clientId;
            $q[$this->authParams['secret']] = $this->secret;
            $q[$this->authParams['code']] = $code;
            $access = $this->parseAccess(file_get_contents($this->accessUrl . '?' .
                        http_build_query($this->addAccessParams($q))));
        } else {
            $access = $this->getAccess($code);
        }
        $profile = [];
        foreach ($access as $nm => $v) {
            $profile[isset($this->profileMap[$nm]) ? $this->profileMap[$nm] : $nm] = $v;
        }
    
        if (
            !isset($profile['email']) ||
            !$profile['email'] ||
            !filter_var($profile['email'], FILTER_VALIDATE_EMAIL)
        ) {
            throw new AuthException(AuthException::OAUTH_NO_EMAIL);
        }
    
        $u = $this->db->get(
            "select * from $this->tableName where email = :email",
            ['email' => $profile['email']]
        );
        if ($u) {
            if (!$u->active) {
                $this->db->execute(
                    "update $this->tableName set active = 1 where uid = :id",
                    ['id' => $u->uid]
                );
            }
        } else {
            $data = [];
            $data['email'] = $profile['email'];
            $data['login'] = $profile['login'] ?? $profile['email'];
            $this->db->execute(
                "insert into $this->tableName (email, login, active) values (:email, :login, 1)",
                $data
            );
            $u = $this->db->get(
                "select * from $this->tableName where email = :email",
                ['email' => $data['email']]
            );
        }
        if (!$u) {
            return $u;
        }
        return new User((string)$u->uid, $u->login, $profile);
    }
  
    protected function addAuthParams(array $params): array
    {
        return $params;
    }
  
    public function authURL()
    {
        $state = bin2hex(random_bytes(10));
        $this->session->set('oauth2state_' . $this->clientId, $state);
        $q = [];
        $q[$this->authParams['cid']] = $this->clientId;
        $q[$this->authParams['state']] = $state;
        $q[$this->authParams['scope']] = $this->scope;
        $q[$this->authParams['redirect']] = $this->callBackUrl;
        return $this->authUrl . '?' . http_build_query($this->addAuthParams($q));
    }
    
    abstract public function handleCallback(Request $req, Response $res);
}
