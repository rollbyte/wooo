<?php
namespace wooo\core\stream;

interface IPipeStarter extends IPipeUnit
{
    public function flush(): void;
}