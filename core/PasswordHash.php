<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class PasswordHash
{
    private $algo;
    
    const DEFAULT = PASSWORD_DEFAULT;
    const BCRYPT = PASSWORD_BCRYPT;
    
    public function __construct($algo = null)
    {
        $this->algo = $algo ?? self::DEFAULT;
        
        $available = [PASSWORD_DEFAULT, PASSWORD_BCRYPT];
        if (defined('PASSWORD_ARGON2I')) {
            $available[] = constant('PASSWORD_ARGON2I');
        }
        if (defined('PASSWORD_ARGON2ID')) {
            $available[] = constant('PASSWORD_ARGON2ID');
        }
        
        if (!in_array($this->algo, $available)) {
            throw new CoreException(CoreException::INVALID_HASH_ALGO);
        }
    }
    
    public function apply($pwd)
    {
        return password_hash($pwd, $this->algo);
    }
    
    public static function check($password, $hash)
    {
        return password_verify($password, $hash);
    }
}
