<?php
namespace wooo\stdlib\dbal\interfaces;

interface SequenceProvider {
	function create(string $name);
	
	function next(string $name);
}