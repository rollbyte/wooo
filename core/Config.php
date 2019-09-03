<?php

namespace wooo\core;

class Config
{
  
    private $config;
  
    public function __construct(array $config = [])
    {
        $this->config = $config;
    }
    
    private function resolve($nm, $default)
    {
        $pos = strpos($nm, '.');
        if ($pos !== false) {
            $base = substr($nm, 0, $pos);
            $path = substr($nm, $pos + 1);
            if (isset($this->config[$base])) {
                if (is_array($this->config[$base])) {
                    $this->config[$base] = new Config($this->config[$base]);
                }
                if ($this->config[$base] instanceof Config) {
                    return $this->config[$base]->get($path, $default);
                }
            }
        }
        return $default;
    }
  
    public function get($name, $default = null)
    {
        return isset($this->config[$name]) ? $this->config[$name] : $this->resolve($name, $default);
    }
  
    public function set($name, $value): Config
    {
        $this->config[$name] = $value;
        return $this;
    }
    
    public function values()
    {
        return $this->config;
    }
    
    public function __get($nm)
    {
        return $this->config[$nm] ?? null;
    }
    
    public function __set($nm, $v): void
    {
        $this->config[$nm] = $v;
    }
    
    public function merge($config)
    {
        $conf = ($config instanceof Config) ? $config->config : $config;
        if (is_array($conf)) {
            $this->config = array_merge($conf, $this->config);
        }
    }
}
