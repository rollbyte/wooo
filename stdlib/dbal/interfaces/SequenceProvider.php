<?php

namespace wooo\stdlib\dbal\interfaces;

interface SequenceProvider
{
  
    public function create(string $name);
  
    public function next(string $name): int;
}
