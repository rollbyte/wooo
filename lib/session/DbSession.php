<?php

namespace wooo\lib\session;

use wooo\core\ISessionHandler;
use wooo\lib\dbal\interfaces\DbDriver;

class DbSession implements ISessionHandler
{
  
    /**
     * @var DbDriver
     */
    private $db;
  
    /**
     *
     * @var string
     */
    private $tableName = 'sessions';
  
    public function __construct(DbDriver $db)
    {
        $this->db = $db;
    }
  
    public function setTableName($name)
    {
        $this->tableName = $name;
    }
  
    /**
     *
     * @param string $sess_path
     * @param string $sess_name
     */
    public function open($sess_path, $sess_name)
    {
        return true;
    }
  
    public function close()
    {
        return true;
    }
  
    /**
     *
     * @param  string $id
     * @return string
     */
    public function read($id)
    {
        $s = $this->db->get(
            "select * from $this->tableName where id = :id",
            [
            'id' => $id
            ]
        );
        return $s ? $s->data : '';
    }
  
    /**
     *
     * @param  string $id
     * @param  string $sess_data
     * @return boolean
     */
    public function write($id, $sess_data)
    {
        $out = [];
        $this->db->execute(
            "update $this->tableName set data = :data where id = :id",
            [
            'data' => $sess_data,
            'id' => $id
            ],
            $out
        );
        if (!isset($out['affected']) || !$out['affected']) {
            $this->db->execute(
                "insert into $this->tableName values (:id, :created, :data)",
                [
                'id' => $id,
                'created' => new \DateTime(),
                'data' => $sess_data
                ]
            );
        }
        return true;
    }
  
    /**
     *
     * @param string $id
     */
    public function destroy($id)
    {
        $this->db->execute("delete from $this->tableName where id=?", [1 => $id]);
        return true;
    }
  
    /**
     *
     * @param int $maxlifetime
     */
    public function gc($maxlifetime)
    {
        $d = new \DateTime();
        $d->setTimestamp($d->getTimestamp() - $maxlifetime);
        $this->db->execute("delete from $this->tableName where created < ?", [1 => $d]);
        return true;
    }
}
