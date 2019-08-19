<?php

namespace wooo\lib\session;

use wooo\lib\dbal\interfaces\DbDriver;

class DbSession implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
  
    /**
     * @var DbDriver
     */
    private $db;
  
    /**
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
  
    public function open($sessionSavePath, $sessionName)
    {
        return true;
    }
  
    public function close()
    {
        return true;
    }
  
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
    public function write($id, $sessData)
    {
        $out = [];
        $this->db->execute(
            "update $this->tableName set data = :data where id = :id",
            [
            'data' => $sessData,
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
                'data' => $sessData
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
        $out = [];
        $this->db->execute("delete from $this->tableName where created < ?", [1 => $d], $out);
        return $out['affected'] ?? 1;
    }

    public function validateId($id)
    {}

    public function updateTimestamp($id, $sessData)
    {}
}
