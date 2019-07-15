<?php

namespace wooo\core;

class Response
{
    private $variables = [];
    
    private $app;
  
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->variables = $app->config()->values();
    }
    
    public function set($nm, $value): Response
    {
        $this->variables[$nm] = $value;
        return $this;
    }
  
    public function redirect($url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host) {
            header("Location: $url", true, 302);
            exit();
        }
        if (!$url || $url[0] != '/') {
            $url = '/' . $url;
        }
        $appBase = $this->app->appBase();
        header("Location: $appBase$url", true, 302);
        exit();
    }
  
    public function render($path, $data)
    {
        extract($this->variables, EXTR_OVERWRITE);
        extract($data, EXTR_OVERWRITE);
        include $path;
        exit();
    }
  
    public function send($data)
    {
        if (is_resource($data)) {
            while (false !== ($chunk = fgetc($data))) {
                echo $chunk;
            }
            fclose($data);
        } elseif (is_string($data)) {
            echo $data;
        } elseif ($data instanceof IStream) {
            while (!$data->eof()) {
                echo $data->read();
            }
            $data->close();
        } elseif (is_array($data) || is_object($data)) {
            $this->setHeader("Content-Type:application/json; charset=utf-8");
            echo json_encode($data);
        }
        exit();
    }
  
    public function setStatus($status): Response
    {
        http_response_code($status);
        return $this;
    }
  
    public function setCookie($name, $value, $expire = null, $path = null, $http_only = false): Response
    {
        if (is_null($value)) {
            $expire = time() - 86400;
        }
    
        if (is_array($value)) {
            $oldval = $_COOKIE[$name];
            if (empty($value)) {
                $unset = array_keys($oldval);
            } elseif (empty($oldval)) {
                $unset = array();
            } else {
                $unset = array_diff(array_keys($oldval), array_keys($value));
            }
      
            foreach ($value as $key => $v) {
                if (!in_array($key, $unset)) {
                    $this->setCookie($name . "[$key]", $v, $expire, $path, $http_only);
                }
            }
      
            foreach ($unset as $u) {
                $this->setCookie($name . "[$u]", null, time() - 86400, $path);
            }
        } else {
            setcookie($name, $value, $expire, $path, null, null, $http_only);
        }
        return $this;
    }
  
    public function setHeader($header): Response
    {
        header($header);
        return $this;
    }
}
