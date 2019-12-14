<?php

namespace wooo\lib\auth;

use wooo\lib\auth\interfaces\IUser;
use wooo\lib\auth\interfaces\IPassport;
use wooo\core\PasswordHash;
use wooo\lib\dbal\interfaces\DbDriver;

/**
 * @author krasilneg
 *
 */
class LocalPassport implements IPassport
{
  
    /**
     * @var \wooo\lib\dbal\interfaces\DbDriver $db
     */
    private $db;
    
    private $tableName = 'user';
  
    public function __construct(DbDriver $db)
    {
        $this->db = $db;
    }

    /**
     *
     * @param string $name
     */
    public function setTableName(string $name)
    {
        $this->tableName = $name;
    }
    
    /**
     * {@inheritDoc}
     * @see \wooo\lib\auth\interfaces\IPassport::authenticate()
     */
    public function authenticate(array $credentials): ?IUser
    {
        $u = $this->db->get(
            "select * from $this->tableName where login = :login and active = 1",
            ['login' => $credentials['login']]
        );
        if ($u) {
            $ph = new PasswordHash();
            if ($ph->check($credentials['pwd'], $u->pwd)) {
                return new User((string)$u->uid, $u->login);
            }
        }
        return null;
    }
    
    /**
     * {@inheritDoc}
     * @param array $credentials ['login' => login, 'pwd' => password]
     * @see \wooo\lib\auth\interfaces\IPassport::applicable()
     */
    public function applicable(array $credentials): bool
    {
        return isset($credentials['login']) && isset($credentials['pwd']);
    }
}
