<?php

namespace wooo\core;

interface ITransactional
{
    public function begin(): void;
    
    public function commit(): bool;
    
    public function rollback(): bool;
}
