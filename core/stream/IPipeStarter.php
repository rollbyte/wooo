<?php

namespace wooo\core\stream;

interface IPipeStarter extends IPipeUnit
{
    public function flush(int $chunkSize = 1024): void;
}
