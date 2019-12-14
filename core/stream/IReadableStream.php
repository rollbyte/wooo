<?php

namespace wooo\core\stream;

interface IReadableStream extends IStream
{
    public const SEEK_START = SEEK_SET;
    public const SEEK_REL = SEEK_CUR;
    public const SEEK_END = SEEK_END;
    
    public function read($length = 1): string;
    public function eof(): bool;
    public function rewind(): void;
    public function seek(int $pos = 0, int $whence = self::SEEK_START): void;
    public function size(): int;
    public function pos(): int;
}
