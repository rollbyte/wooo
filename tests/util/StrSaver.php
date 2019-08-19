<?php
namespace wooo\tests\util;

use wooo\core\stream\IWritableStream;

class StrSaver implements IWritableStream
{
    private $buffer = '';
    
    public function write(string $data): int
    {
        $this->buffer .= $data;
        return strlen($data);
    }

    public function close(): void
    {}
    
    public function getContents(): string
    {
        return $this->buffer;
    }
}