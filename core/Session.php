<?php

namespace wooo\core;

class Session
{
    private $isOpen = false;
  
    private $name = null;
  
    private $domain = null;
  
    private $handler;
  
    public function override(ISessionHandler $handler)
    {
        $this->handler = $handler;
    }
  
    public function __construct(Config $config)
    {
        $this->name = $config->get('SESSION_NAME', 'wooo');
    }
    
    public function setDomain($domain): void
    {
        $this->domain = $domain;
    }
  
    private function open()
    {
        if (!$this->isOpen) {
            session_name($this->name . '_SESSID');
            if ($this->domain) {
                ini_set('session.cookie_domain', $this->domain);
            }
            if ($this->handler) {
                session_set_save_handler(
                    array(
                    $this->handler,
                    "open"
                    ),
                    array(
                    $this->handler,
                    "close"
                    ),
                    array(
                    $this->handler,
                    "read"
                    ),
                    array(
                    $this->handler,
                    "write"
                    ),
                    array(
                    $this->handler,
                    "destroy"
                    ),
                    array(
                    $this->handler,
                    "gc"
                    )
                );
            }
            session_start();
            $this->isOpen = true;
        }
    }
  
    public function get($name, $default = null)
    {
        $this->open();
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
    }
  
    public function set($name, $value)
    {
        $this->open();
        $_SESSION[$name] = $value;
    }
  
    public function reset()
    {
        $this->open();
        return session_regenerate_id(true);
    }
  
    public function id()
    {
        return $this->isOpen ? session_id() : null;
    }
}