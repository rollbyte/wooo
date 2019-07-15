<?php
namespace wooo\stdlib\dbal;

use wooo\stdlib\dbal\interfaces\DbCursor;

class PDOCursor implements DbCursor
{
    
    private $statement;
    
    private $current;
    
    private $counter = 0;
    
    private $q;
    
    private $caster;
    
    public function __construct(\PDOStatement $stmt, string $q, callable $caster)
    {
        $this->statement = $stmt;
        $this->q = $q;
        $this->caster = $caster;
    }
    
    public function current()
    {
        return $this->current;
    }
    
    public function key()
    {
        return $counter;
    }
    
    public function next()
    {
        try {
            $v = $this->caster;
            $this->current = $v($this->statement->fetchObject());
        } catch (\Exception $e) {
            $this->current = false;
            $this->statement->closeCursor();
            throw new DbException(DbException::FETCH_FAILED, [$this->q, 'not available in cursor'], $e);
        }
        
        if ($this->current === false) {
            $this->statement->closeCursor();
            return false;
        } else {
            $this->counter ++;
            return true;
        }
    }
    
    public function rewind()
    {
        return;
    }
    
    public function valid()
    {
        $result = ($this->current !== false);
        if (!$result) {
            $this->statement->closeCursor();
        }
        return $result;
    }
    
    public function close(): void
    {
        $this->statement->closeCursor();
    }
}
