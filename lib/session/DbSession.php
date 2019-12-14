<?php

namespace wooo\lib\session;

use wooo\lib\dbal\interfaces\DbDriver;
use wooo\core\DateTime;

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

    public function setTableName(string $name): void
    {
        $this->tableName = $name;
    }
  
    /**
     *
     * {@inheritDoc}
     * @see \SessionHandlerInterface::open()
     */
    public function open($sessionSavePath, $sessionName)
    {
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \SessionHandlerInterface::close()
     */
    public function close()
    {
        return true;
    }
    
    /**
     * {@inheritDoc}
     * @see \SessionHandlerInterface::read()
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
    
    private function create($id, $sessData)
    {
        $this->db->execute(
            "insert into $this->tableName values (:id, :created, :data)",
            [
                'id' => $id,
                'created' => new DateTime(),
                'data' => $sessData
            ]
        );
    }
  
    /**
     * {@inheritDoc}
     * @see \SessionHandlerInterface::write()
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
            $this->create($id, $sessData);
        }
        return true;
    }
  
    /**
     * {@inheritDoc}
     * @see \SessionHandlerInterface::destroy()
     */
    public function destroy($id)
    {
        $this->db->execute("delete from $this->tableName where id=?", [1 => $id]);
        return true;
    }

    /**
     * {@inheritDoc}
     * @see \SessionHandlerInterface::gc()
     */
    public function gc($maxlifetime)
    {
        $d = new \DateTime();
        $d->setTimestamp($d->getTimestamp() - $maxlifetime);
        $out = [];
        $this->db->execute("delete from $this->tableName where created < ?", [1 => $d], $out);
        return $out['affected'] ?? 1;
    }

    /**
     * {@inheritDoc}
     * @see \SessionUpdateTimestampHandlerInterface::validateId()
     */
    public function validateId($id)
    {
        return $this->db->get("select id from $this->tableName where id = :id", ['id' => $id]) ? true : false;
    }

    /**
     * {@inheritDoc}
     * @see \SessionUpdateTimestampHandlerInterface::updateTimestamp()
     */
    public function updateTimestamp($id, $sessData)
    {
        $out = [];
        $this->db->execute(
            "update $this->tableName set created = :created where id = :id",
            [
                'created' => new DateTime(),
                'id' => $id
            ],
            $out
        );
        if (!isset($out['affected']) || !$out['affected']) {
            $this->create($id, $sessData);
        }
        return true;
    }
}
