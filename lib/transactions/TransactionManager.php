<?php

namespace wooo\lib\transactions;

use wooo\core\ITransactional;
use wooo\core\ILog;

class TransactionManager implements ITransactional
{
    /**
     * @var ITransactional[]
     */
    private $transactions = [];
    
    private $log;
    
    private $inTransaction = false;
    
    public function __construct(ILog $log)
    {
        $this->log = $log;
    }
    
    public function __set($nm, $value)
    {
        if ($nm == 'manage' && $value instanceof ITransactional) {
            $this->transactions[] = $value;
        }
    }
    
    public function rollback(): bool
    {
        if ($this->inTransaction) {
            foreach ($this->transactions as $t) {
                try {
                    $t->rollback();
                } catch (\Exception $e) {
                    $this->log->error($e);
                }
            }
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function commit(): bool
    {
        if ($this->inTransaction) {
            foreach ($this->transactions as $t) {
                try {
                    $t->commit();
                } catch (\Exception $e) {
                    $this->log->error($e);
                }
            }
            $this->inTransaction = false;
            return true;
        }
        return false;
    }

    public function begin(): void
    {
        foreach ($this->transactions as $t) {
            $t->begin();
        }
        $this->inTransaction = true;
    }
}
