<?php

namespace wooo\stdlib\dbal\interfaces;

interface DbCursor extends \Iterator
{
  
    public function close(): void;
}
