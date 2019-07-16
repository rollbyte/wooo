<?php

namespace wooo\lib\dbal;

use wooo\lib\dbal\interfaces\SequenceProvider;

class SQLSequenceProvider implements SequenceProvider
{
  
    /**
     * @var \wooo\lib\dbal\interfaces\DbDriver
     */
    private $driver;
    
    protected $tableName = 'sequences';
  
    public function __construct(PDODriver $driver)
    {
        $this->driver = $driver;
    }
    
    public function setTableName(string $name): void
    {
        $this->tableName = $name;
    }
    
    protected function curValQuery()
    {
        return "select value from $this->tableName where name = :nm";
    }
  
    public function next(string $name): int
    {
        $this->driver->begin();
        try {
            $cur = $this->driver->query(
                $this->curValQuery(),
                ['nm' => $name]
            );
            $result = null;
            if (empty($cur)) {
                $result = 1;
                $this->driver->execute(
                    "insert into $this->tableName (name, value) values (:nm, 1)",
                    ['nm' => $name]
                );
            } else {
                $result = $cur[0]->value + 1;
                $this->driver->execute(
                    "update $this->tableName set value = :v where name = :nm",
                    [
                    'v' => $result,
                    'nm' => $name
                    ]
                );
            }
            $this->driver->commit();
            return $result;
        } catch (\Exception $e) {
            $this->driver->rollback();
            throw new DbException(DbException::SEQUENCE_FAILED, [$name], $e);
        }
    }
  
    public function create(string $name)
    {
        $this->driver->execute(
            "insert into $this->tableName (name, value) values (:nm, 0)",
            ['nm' => $name]
        );
    }
}
