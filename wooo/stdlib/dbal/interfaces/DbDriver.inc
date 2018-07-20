<?php
namespace wooo\stdlib\dbal\interfaces;

interface DbDriver {
	function execute(string $q, array $params, ?array &$output = null): DbDriver;
	
	function query(string $q, array $params): array;
	
	function scalar(string $q, array $params);
	
	function get(string $q, array $params): ?object;
	
	function iterate(string $q, array $params): DbCursor;
	
	function begin(): void;
	
	function commit(): void;
	
	function rollback(): void;
}