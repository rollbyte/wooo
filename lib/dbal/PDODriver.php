<?php

namespace wooo\lib\dbal;

use wooo\lib\dbal\interfaces as wooo;
use wooo\lib\dbal\interfaces\DbDriver;
use wooo\lib\dbal\interfaces\DbCursor;
use wooo\core\DateTime;

class PDODriver implements wooo\DbDriver
{
  
    /**
     *
     * @var \PDO
     */
    private $connection;
  
    /**
     *
     * @var string
     */
    private $uri;
  
    /**
     *
     * @var string
     */
    private $user;
  
    /**
     *
     * @var string
     */
    private $pwd;
  
    private $prepared = [];
    
    private $transaction_level = 0;
    
    private $connectionOptions = [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION];
    
    private $dateTimeFormat = 'Y-m-d H:i:s';
    
    private $dateTimeZone = false;
  
    public function __construct($uri, string $user = null, string $pwd = null)
    {
        if ($uri instanceof \PDO) {
            $this->connection = $uri;
        } else {
            $this->uri = $uri;
            $this->user = $user;
            $this->pwd = $pwd;
        }
    }
    
    public function setConnectionOptions($options)
    {
        if (is_array($options)) {
            foreach ($options as $nm => $value) {
                if (defined('\PDO::' . $nm)) {
                    $this->connectionOptions[constant('\PDO::' . $nm)] = $value;
                }
            }
        }
    }
    
    public function setDateTimeFormat($format)
    {
        $this->dateTimeFormat = $format;
    }
    
    public function setDateTimeZone(string $tz)
    {
        $this->dateTimeZone = $tz;
    }
    
    private function connect()
    {
        if (!$this->connection) {
            $this->connection = new \PDO(
                $this->uri,
                $this->user,
                $this->pwd,
                $this->connectionOptions
            );
        }
    }
  
    private function valType($v)
    {
        if (is_int($v)) {
            return \PDO::PARAM_INT;
        }
        
        if (is_bool($v)) {
            return \PDO::PARAM_BOOL;
        }
        return \PDO::PARAM_STR;
    }
  
    /**
     *
     * @param  string $q
     * @param  array  $params
     * @param  array  $output
     * @return \PDOStatement
     */
    private function statement($q, $params, &$output = null)
    {
        $this->connect();
        if (
            !isset($this->prepared[$q]) ||
            ($this->prepared[$q]->errorCode() && $this->prepared[$q]->errorCode() !== '00000')
        ) {
            $this->prepared[$q] = $this->connection->prepare($q);
        }
        if (is_array($params)) {
            foreach ($params as $nm => $value) {
                if ($value instanceof \DateTime) {
                    if ($this->dateTimeZone) {
                        $value = $value->setTimezone(new \DateTimeZone($this->dateTimeZone));
                    } else {
                        $env_tz = date_default_timezone_get();
                        if ($value->getTimezone()->getName() !== $env_tz) {
                            $value->setTimezone(new \DateTimeZone($env_tz));
                        }
                    }
                    $value = $value->format($this->dateTimeFormat);
                }
                $this->prepared[$q]->bindValue($nm, $value, $this->valType($value));
            }
        }
        if (is_array($output)) {
            foreach ($output as $nm => $value) {
                $this->prepared[$q]->bindParam($nm, $value, \PDO::PARAM_INPUT_OUTPUT);
            }
        }
        return $this->prepared[$q];
    }
  
    public function execute(string $q, array $params = [], ?array &$output = null): DbDriver
    {
        $out = $output ?? [];
        $stmt = $this->statement($q, $params, $out);
        try {
            $stmt->execute();
            if (is_array($output)) {
                $output['affected'] = $stmt->rowCount();
                if (preg_match('/^\\s*insert\\s.*$/m', $q)) {
                    $output['rowid'] = $this->connection->lastInsertId();
                }
            }
            return $this;
        } catch (\Exception $e) {
            throw new DbException(DbException::EXEC_FAILED, [$q, json_encode($params)], $e);
        }
    }
    
    private function cast($v, $type)
    {
        switch ($type) {
            case \DateTime::class:
            case 'date':
            case 'datetime':
                if ($v === '' || $v === null) {
                    return null;
                }
                if (is_string($v)) {
                    if ($this->dateTimeFormat) {
                        $d = \DateTime::createFromFormat(
                            $this->dateTimeFormat,
                            $v,
                            $this->dateTimeZone ? new \DateTimeZone($this->dateTimeZone) : null
                        );
                        return new DateTime($d);
                    } else {
                        return new DateTime(
                            $v,
                            $this->dateTimeZone ? new \DateTimeZone($this->dateTimeZone) : null
                        );
                    }
                }
                break;
            case 'int':
                if (!is_int($v)) {
                    return ($v === '' || $v === null) ? null : intval($v);
                }
                break;
            case 'float':
                if (!is_float($v)) {
                    $v = ($v === '' || $v === null) ? null : floatval($v);
                }
                break;
            case 'bool':
                if (!is_bool($v)) {
                    $v = boolval($v);
                }
                break;
        }
        return $v;
    }
    
    private function processResult(&$obj, array $types)
    {
        foreach ($types as $nm => $type) {
            if (isset($obj->$nm)) {
                $obj->$nm = $this->cast($obj->$nm, $type);
            }
        }
        return $obj;
    }
  
    public function query(string $q, array $params = [], array $types = []): array
    {
        $stmt = $this->statement($q, $params);
        try {
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_OBJ);
            $stmt->closeCursor();
            array_walk($result, function (&$obj) use ($types) {
                $this->processResult($obj, $types);
            });
            return $result;
        } catch (\Exception $e) {
            $stmt->closeCursor();
            throw new DbException(DbException::FETCH_FAILED, [$q, json_encode($params)], $e);
        }
    }
  
    public function get(string $q, array $params = [], array $types = []): ?object
    {
        $stmt = $this->statement($q, $params);
        try {
            $stmt->execute();
            $result = $stmt->fetchObject();
            $stmt->closeCursor();
            return $result ? $this->processResult($result, $types) : null;
        } catch (\Exception $e) {
            $stmt->closeCursor();
            throw new DbException(DbException::FETCH_FAILED, [$q, json_encode($params)], $e);
        }
    }
  
    public function iterate(string $q, array $params = [], array $types = []): DbCursor
    {
        $stmt = $this->statement($q, $params);
        try {
            $stmt->execute();
            return new PDOCursor($stmt, $q, function ($obj) use ($types) {
                return $this->processResult($obj, $types);
            });
        } catch (\Exception $e) {
            $stmt->closeCursor();
            throw new DbException(DbException::FETCH_FAILED, [$q, json_encode($params)], $e);
        }
    }
  
    public function begin(): void
    {
        $this->connect();
        if ($this->connection) {
            if (!$this->connection->inTransaction()) {
                $this->connection->beginTransaction();
            }
            $this->transaction_level++;
        }
    }
  
    public function commit(): void
    {
        if ($this->connection && $this->connection->inTransaction()) {
            if ($this->transaction_level < 2) {
                $this->connection->commit();
            }
            $this->transaction_level--;
        }
    }
  
    public function rollback(): void
    {
        if ($this->connection && $this->connection->inTransaction()) {
            $this->connection->rollback();
            $this->transaction_level--;
        }
    }
  
    public function scalar(string $q, array $params = [], ?string $type = null)
    {
        try {
            $stmt = $this->statement($q, $params);
            $stmt->execute();
            $result = $this->cast($stmt->fetchColumn(), $type);
            $stmt->closeCursor();
            return $result;
        } catch (\Exception $e) {
            throw new DbException(DbException::FETCH_FAILED, [$q, json_encode($params)], $e);
        }
    }
}
