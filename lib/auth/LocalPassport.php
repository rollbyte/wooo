<?php

namespace wooo\lib\auth;

use wooo\lib\auth\interfaces\IUser;
use wooo\lib\auth\interfaces\IPassport;
use wooo\core\PasswordHash;
use wooo\lib\dbal\interfaces\DbDriver;

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
    
    public function setTableName($name)
    {
        $this->tableName = $name;
    }
  
    public function authorise(array $credentials): ?IUser
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
}
