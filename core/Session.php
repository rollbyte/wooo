<?php

namespace wooo\core;

class Session
{
    private $isOpen = false;
  
    private $name = null;
  
    private $domain = null;
  
    private $handler;
    
    private static $instance;
  
    public function override(\SessionHandlerInterface $handler): Session
    {
        $this->handler = $handler;
        return $this;
    }
  
    private function __construct(?Config $config = null)
    {
        $this->name = $config ? $config->get('SESSION_NAME', 'wooo') : 'wooo';
    }
    
    public static function instance(?Config $config = null)
    {
        if (!self::$instance) {
            self::$instance = new Session($config);
        } else {
            self::$instance->name = $config ? $config->get('SESSION_NAME', 'wooo') : 'wooo';
        }
        return self::$instance;
    }
    
    public function setDomain($domain): Session
    {
        $this->domain = $domain;
        return $this;
    }
  
    private function open()
    {
        if (!$this->isOpen) {
            session_name($this->name . '_SESSID');
            if ($this->domain) {
                ini_set('session.cookie_domain', $this->domain);
            }
            if ($this->handler) {
                session_set_save_handler($this->handler, true);
            }
            session_start();
            $this->isOpen = true;
        }
    }
  
    public function get(string $name, $default = null)
    {
        $this->open();
        return isset($_SESSION[$name]) ? $_SESSION[$name] : $default;
    }
  
    public function set(string $name, $value): Session
    {
        $this->open();
        $_SESSION[$name] = $value;
        return $this;
    }
  
    public function reset(): string
    {
        $this->open();
        return session_regenerate_id(true);
    }
    
    public function close(): Session
    {
        session_write_close();
        $this->isOpen = false;
        return $this;
    }
  
    public function id(): string
    {
        return $this->isOpen ? session_id() : null;
    }
}
