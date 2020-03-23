<?php

namespace wooo\core\events;

trait EventEmitter
{
    protected $handlers = [];
    
    public function on(string $code, callable $handler, array $data = [])
    {
        if (!isset($this->handlers[$code])) {
            $this->handlers[$code] = [];
        }
        $this->handlers[$code][] = [
            'handler' => $handler,
            'data' => $data
        ];
    }
    
    protected abstract function callEventHandler(IEvent $event, callable $handler, array $data = []);
    
    protected function raise(IEvent $event)
    {
        if (isset($this->handlers[$event->code()])) {
            if (is_array($this->handlers[$event->code()])) {
                foreach ($this->handlers[$event->code()] as $b) {
                    $result = $this->callEventHandler($event, $b['handler'], $b['data']);
                    if ($result === false || $event->isPrevented()) {
                        return false;
                    }
                }
            }
        }
        return true;
    }
}
