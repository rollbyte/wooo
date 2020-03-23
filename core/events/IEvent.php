<?php

namespace wooo\core\events;

interface IEvent
{
    public function code(): string;
    
    public function data(): array;
    
    public function emitter(): IEventEmitter;
    
    public function mark(string $marker);
    
    public function hasMark(string $marker);
    
    public function prevent();
    
    public function isPrevented(): bool;
}
