<?php
namespace wooo\stdlib\dbal\interfaces;

interface DbCursor extends \Iterator {
	function close(): void;
}
