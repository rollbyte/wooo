<?php

namespace wooo\core\events;

interface IEventEmitter
{
    public function on(string $code, callable $handler, array $data = []);
}
