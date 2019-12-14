<?php

namespace wooo\core;

class Router
{
    protected $map = [];
    
    private function forceMethod($method, $args): array
    {
        $result = array_filter(
            $args,
            function ($arg) use ($method) {
                return !is_string($arg) || (strtoupper($arg) != $method);
            }
        );
        array_walk(
            $result,
            function (&$arg) use ($method) {
                if (is_array($arg)) {
                    $arg = $this->forceMethod($method, $arg);
                    if (empty($arg)) {
                        $arg = false;
                    }
                }
            }
        );
        return array_filter($result);
    }
    
    public function use($arg0): Router
    {
        $this->map[] = func_get_args();
        return $this;
    }
    
    public function get($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['GET'], $this->forceMethod('GET', $args));
        return $this;
    }
    
    public function post($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['POST'], $this->forceMethod('POST', $args));
        return $this;
    }
    
    public function delete($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['DELETE'], $this->forceMethod('DELETE', $args));
        return $this;
    }
    
    public function put($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['PUT'], $this->forceMethod('PUT', $args));
        return $this;
    }
    
    public function patch($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['PATCH'], $this->forceMethod('PATCH', $args));
        return $this;
    }
    
    public function head($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['HEAD'], $this->forceMethod('HEAD', $args));
        return $this;
    }
    
    public function search($arg0): Router
    {
        $args = func_get_args();
        $this->map[] = array_merge(['SEARCH'], $this->forceMethod('SEARCH', $args));
        return $this;
    }
}
