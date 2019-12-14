<?php

namespace wooo\core\stream;

interface IWritableStream extends IStream
{
    public function write(string $data): int;
}
