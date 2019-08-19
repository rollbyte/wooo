<?php
namespace wooo\core\stream;

abstract class TransformStream implements IWritableStream, IPipeUnit
{
    use PipeUnitTrait;
    
    public function close(): void
    {
        $this->emit('close');    
    }

    protected abstract function transform($data);
    
    public function write(string $data): int
    {
        $result = $this->transform($data);
        if ($result !== null) {
            $this->emit('data', $result);
            return strlen($result);
        }
        return 0;
    }
}