<?php

namespace wooo\core\events;

abstract class Event implements IEvent
{
    protected $marks = [];
    
    protected $prevented = false;

    protected $code;
    
    protected $data = [];
    
    protected $emitter;
    
    public function __construct(string $code, IEventEmitter $emitter, array $data = [])
    {
        $this->code = $code;
        $this->emitter = $emitter;
        $this->data = $data;
    }
    
    public function hasMark(string $marker)
    {
        return isset($this->marks[$marker]);
    }

    public function prevent()
    {
        $this->prevented = true;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function isPrevented(): bool
    {
        return $this->prevented;
    }

    public function emitter(): IEventEmitter
    {
        return $this->emitter;
    }

    public function mark(string $marker)
    {
        $this->marks[$marker] = true;   
    }
}
