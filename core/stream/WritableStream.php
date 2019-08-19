<?php
namespace wooo\core\stream;

use wooo\core\exceptions\CoreException;

class WritableStream implements IWritableStream
{
    
    private $resource;
    
    public function __construct($res)
    {
        if (!is_resource($res)) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED);
        }
        $meta = stream_get_meta_data($res);
        if (strpbrk($meta['mode'], 'waxc') === false) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED);
        }
        $this->resource = $res;
    }
    
    public function write(string $data): int
    {
        return fwrite($this->resource, $data);
    }

    public function close(): void
    {
        fclose($this->resource);
    }
}