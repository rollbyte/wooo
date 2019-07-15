<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class Stream implements IStream
{
    private $resource;
    
    public function __construct($res)
    {
        if (!is_resource($res)) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED);
        }
        $this->resource = $res;
    }
    
    public function read($length = 1): string
    {
        if ($length > 1) {
            $chunk = fread($this->resource, $length);
        } else {
            $chunk = fgetc($this->resource);
        }
        return $chunk;
    }

    public function eof(): bool
    {
        return feof($this->resource);
    }
    public function rewind(): void
    {
        if (rewind($this->resource) !== true) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED, ['stream rewind']);
        }
    }

    public function close(): void
    {
        fclose($this->resource);
    }
    
    public function size(): int
    {
        $stat = fstat($this->resource);
        return $stat['size'];
    }
    
    public function pos(): int
    {
        return ftell($this->resource);
    }

    public function seek(int $pos = 0, int $whence = self::SEEK_START): void
    {
        if (fseek($this->resource, $pos, $whence) !== 0) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED, ['stream seek']);
        }
    }
}
