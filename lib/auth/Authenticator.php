<?php
namespace wooo\lib\auth;

use wooo\lib\auth\interfaces as auth;
use wooo\core\Request;
use wooo\lib\auth\interfaces\IAuthenticator;
use wooo\lib\auth\interfaces\IPassport;
use wooo\lib\auth\interfaces\IUser;

abstract class Authenticator implements auth\IAuthenticator
{
    
    protected $passports = [];
    
    public function __construct(Request $req)
    {
        $this->session = $req->session();
    }
    
    public function setup(string $name, IPassport $passport)
    {
        $this->passports[$name] = $passport;
    }
    
    protected abstract function store(IUser $user): void;
    
    public function login(array $credentials, ?string $passport = null): void
    {   
        if (!$passport) {
            foreach ($this->passports as $t => $h) {
                if ($h->applicable($credentials)) {
                    $passport = $t;
                    break;
                }
            }
        }
        
        if (!$passport) {
            $passport = IAuthenticator::TYPE_LOCAL;
        }
        
        if (isset($this->passports[$passport])) {
            /**
             *
             * @var auth\IPassport
             */
            $p = $this->passports[$passport];
            
            $u = $p->authenticate($credentials);
            if ($u) {
                $this->store($u);
                return;
            }
            throw new AuthException(AuthException::INVALID_CREDENTIALS);
        }
        throw new AuthException(AuthException::NO_PASSPORT, [$passport]);
    }
    
    public function passports(): array
    {
        return $this->passports;
    }
}