<?php
namespace wooo\core;

class Router
{
    protected $map = [];
    
    private function addRoute($path, $meta)
    {
        if (!isset($this->map[$path])) {
            $this->map[$path] = [];
        }
        $this->map[$path][] = $meta;
    }
    
    public function use($arg0, $arg1 = null, $arg2 = null): Route
    {
        $path = $arg0;
        $module = $arg1;
        $method = $arg2;
        if (!$module) {
            $module = $path;
            $path = null;
        }
        
        if ($module) {
            $meta = ['handler' => $module];
            if ($method) {
                $meta['method'] = $method;
            }
            
            if ($path) {
                if (is_array($path)) {
                    foreach ($path as $pth) {
                        $this->addRoute($pth, $meta);
                    }
                } elseif (is_string($path)) {
                    $this->addRoute($path, $meta);
                }
            } else {
                $this->addRoute('', $meta);
            }
        }
        
        return $this;
    }
    
    public function get($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'GET');
    }
    
    public function post($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'POST');
    }
    
    public function delete($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'DELETE');
    }
    
    public function put($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'PUT');
    }
    
    public function patch($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'PATCH');
    }
    
    public function head($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'HEAD');
    }
    
    public function search($arg0, $arg1 = null): Route
    {
        return $this->use($arg0, $arg1, 'SEARCH');
    }
}
