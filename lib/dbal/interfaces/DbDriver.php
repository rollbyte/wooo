<?php

namespace wooo\lib\dbal\interfaces;

interface DbDriver
{
  
    public function execute(string $q, array $params = [], ?array &$output = null): DbDriver;
  
    public function query(string $q, array $params = [], array $types = []): array;
  
    public function scalar(string $q, array $params = [], ?string $type = null);
  
    public function get(string $q, array $params = [], array $types = []): ?object;
  
    public function iterate(string $q, array $params = [], array $types = []): DbCursor;
  
    public function begin(): void;
  
    public function commit(): void;
  
    public function rollback(): void;
}
