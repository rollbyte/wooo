<?php

namespace wooo\lib\auth;

use wooo\lib\auth\interfaces\IUser;
use wooo\core\Request;

class SessionAuthenticator extends Authenticator
{
  
    /**
     * @var \wooo\core\Session
     */
    private $session;
    
    public function __construct(Request $req)
    {
        $this->session = $req->session();
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
     * @see \wooo\lib\auth\interfaces\IAuthenticator::user()
     */
    public function user(): ?IUser
    {
        return $this->session->get('curr_user');
    }
  
    protected function store(IUser $user): void
    {
        $this->session->set('curr_user', $user);
    }
}
