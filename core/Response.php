<?php

namespace wooo\core;

use wooo\core\stream\IReadableStream;

class Response
{
    private $variables = [];
    
    private $app;
    
    private $engine;
  
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->variables = $app->config()->values();
    }
    
    public function setTemplateEngine(?ITemplateEngine $engine): Response
    {
        $this->engine = $engine;
        return $this;
    }
    
    public function set(string $nm, $value): Response
    {
        $this->variables[$nm] = $value;
        return $this;
    }
    
    public function __set(string $nm, $value): void
    {
        $this->set($nm, $value);
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
  
    public function render(string $path, array $data = [])
    {
        if ($this->engine) {
            $this->engine->render($path, array_merge($data, $this->variables));
        } else {
            extract($this->variables, EXTR_OVERWRITE);
            extract($data, EXTR_OVERWRITE);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (!$ext) {
                $path = $path . '.php';
            }
            include $path;
        }
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
        } elseif ($data instanceof IReadableStream) {
            while (!$data->eof()) {
                echo $data->read(1024);
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
  
    public function setCookie(string $name, $value, ?int $expire = null, ?string $path = null, ?bool $http_only = false): Response
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
            if (!is_null($v) && ($key = $this->app->config()->get('cookieValidationKey', false))) {
                $h = new Hash(Hash::SHA256);
                $hash = $h->apply($value, $key);
                $value = base64_encode($hash . $value);
            }
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
