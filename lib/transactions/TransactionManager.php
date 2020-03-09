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
    
    public function rollback(): void
    {
        foreach ($this->transactions as $t) {
            try {
                $t->rollback();
            } catch (\Exception $e) {
                $this->log->error($e);
            }
        }
    }

    public function commit(): void
    {
        foreach ($this->transactions as $t) {
            try {
                $t->commit();
            } catch (\Exception $e) {
                $this->log->error($e);
            }
        }
    }

    public function begin(): void
    {
        foreach ($this->transactions as $t) {
            $t->begin();
        }
    }
}
