<?php

namespace wooo\core;

use wooo\core\stream\IReadableStream;
use wooo\core\events\IEvent;
use wooo\core\events\IEventEmitter;
use wooo\core\events\EventEmitter;
use wooo\core\events\response\RenderEvent;
use wooo\core\events\Event;
use wooo\core\events\response\SendEvent;
use wooo\core\events\response\RedirectEvent;

class Response implements IEventEmitter
{
    use EventEmitter;
    
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
    
    public function __get($nm)
    {
        return $this->variables[$nm] ?? null;
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
        $this->raise(new class($this, "$appBase$url") extends RedirectEvent {
            public function __construct(Response $r, string $url)
            {
                parent::__construct($r, $url);
            }
        });
        header("Location: $appBase$url", true, 302);
        $this->app->exit();
    }
  
    public function render(string $path, array $data = [])
    {
        $this->raise(new class($this, $path, $data) extends RenderEvent {
            public function __construct(Response $r, string $tpl, array $vars)
            {
                parent::__construct($r, $tpl, $vars);
            }
        });
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
        $this->app->exit();
    }
    
    public function isSendable($data)
    {
        return !is_null($data);
    }
  
    public function send($data)
    {
        $this->raise(new class($this, $data) extends SendEvent {
            public function __construct(Response $r, $data)
            {
                parent::__construct($r, $data);
            }
        });
        if (is_resource($data)) {
            while (false !== ($chunk = fgetc($data))) {
                echo $chunk;
            }
            fclose($data);
        } elseif ($data instanceof IReadableStream) {
            while (!$data->eof()) {
                echo $data->read(1024);
            }
            $data->close();
        } elseif ($data instanceof \DateTime) {
            echo $data->format(\DateTime::ISO8601);
        } elseif (is_array($data) || is_object($data)) {
            $this->setHeader("Content-Type:application/json; charset=utf-8");
            echo json_encode($data);
        } elseif (!is_null($data)) {
            echo strval($data);
        }
        $this->app->exit();
    }
  
    public function setStatus($status): Response
    {
        http_response_code($status);
        return $this;
    }
  
    public function setCookie(
        string $name,
        $value,
        ?int $expire = null,
        ?string $path = null,
        ?bool $http_only = false
    ): Response {
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
    
    protected function callEventHandler(IEvent $event, callable $handler, array $data = [])
    {
        return $handler($event, $data);
    }
}
