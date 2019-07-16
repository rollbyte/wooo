<?php

namespace wooo\lib\dbal\interfaces;

interface DbCursor extends \Iterator
{
  
    public function close(): void;
}
