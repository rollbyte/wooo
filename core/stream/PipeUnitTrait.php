<?php
namespace wooo\core\stream;

use wooo\core\exceptions\CoreException;

trait PipeUnitTrait
{
    private $listeners = [];
    
    protected function emit(string $event, $data = null)
    {
        if (isset($this->listeners[$event]) && is_array($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $h) {
                if (is_callable($h)) {
                    $h($data);
                }
            }
        }
    }
    
    private function on(string $event, callable $handler)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $handler;
    }
    
    public function pipe(IWritableStream $destination, bool $autoClose = true): IPipeStarter
    {
        $this->on(
            'data',
            function ($data) use ($destination) {
                $destination->write($data);
            }
        );
        
        if ($autoClose) {
            $this->on(
                'close',
                function () use ($destination) {
                    $destination->close();
                }
            );
        }
        
        return (
            new class($this, $destination) implements IPipeStarter
            {
                private $base;
                private $flushers = [];
                    
                public function __construct(IPipeUnit $starter, IWritableStream $base)
                {
                    if ($starter instanceof IPipeStarter) {
                        $this->flushers[] = $starter;
                    }
                    $this->base = $base;
                }
                public function pipe(IWritableStream $destination, bool $autoClose = true): IPipeStarter
                {
                    if (!($this->base instanceof IPipeUnit)) {
                        throw new CoreException(CoreException::NOT_PIPE_UNIT, [get_class($this->base)]);
                    }
                    $this->base = $this->base->pipe($destination, $autoClose);
                    $this->flushers[] = $this->base;
                    return $this;
                }
                public function flush(int $chunkSize = 1024): void
                {
                    foreach ($this->flushers as $f) {
                        $f->flush($chunkSize);
                    }
                }
            }
        );
    }
}