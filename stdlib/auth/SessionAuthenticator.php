<?php

namespace wooo\stdlib\auth;

use wooo\stdlib\auth\interfaces as auth;
use wooo\stdlib\auth\interfaces\IUser;
use wooo\core\Request;

class SessionAuthenticator implements auth\IAuthenticator
{
  
    private $passportTypes = [];
  
    private $passports = [];
    
    /**
     * @var \wooo\core\Session
     */
    private $session;
    
    public function __construct(Request $req)
    {
        $this->session = $req->session();
    }
  
    public function setPassport(auth\IPassport $passport)
    {
        array_push($this->passports, $passport);
    }
  
    public function setPassportType(string $type)
    {
        array_push($this->passportTypes, $type);
    }
  
    protected function passportMap()
    {
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
  
    public function login(array $credentials, string $type = auth\IAuthenticator::TYPE_LOCAL): void
    {
        $combine = $this->passportMap();
    
        if (isset($combine[$type])) {
            /**
             *
             * @var auth\IPassport
             */
            $p = $combine[$type];
      
            $u = $p->authorise($credentials);
            if ($u) {
                $this->session->set('curr_user', $u);
                return;
            }
            throw new AuthException(AuthException::INVALID_CREDENTIALS);
        }
        throw new AuthException(AuthException::NO_PASSPORT, [$type]);
    }
  
    public function force(IUser $user): void
    {
        $this->session->set('curr_user', $user);
    }
  
    public function logout(): void
    {
        $this->session->set('curr_user', null);
    }
  
    /**
     * {@inheritdoc}
     *
     * @see \wooo\stdlib\auth\interfaces\IAuthenticator::user()
     */
    public function user(): ?IUser
    {
        return $this->session->get('curr_user');
    }
  
    public function passports(): array
    {
        return $this->passportMap();
    }
}
