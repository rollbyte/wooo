<?php
namespace wooo\core;

interface ITransactional
{
    public function begin(): void;
    
    public function commit(): void;
    
    public function rollback(): void;
}