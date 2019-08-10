<?php

namespace wooo\core;

use wooo\core\exceptions\ComponentNotFoundException;
use wooo\core\exceptions\ScopeException;
use Psr\Container\ContainerInterface;

class Scope implements ContainerInterface
{
    /**
     * @var \wooo\core\App
     */
    private $app;
  
    private $di;
  
    private $registry = [];
  
    public function __construct(App $app, array $di)
    {
        $this->app = $app;
        $this->di = $di;
    }
  
    private function parseValue($v, $type = null)
    {
        if (is_string($v)) {
            $v = preg_replace_callback(
                '/\\$\\{([a-z0-9_]+)\\}/i',
                function ($m) {
                    switch ($m[1]) {
                        case 'APP_PATH':
                            return $this->app->appPath();
                        case 'APP_BASE':
                            return $this->app->appBase();
                        default:
                            return $this->app->config()->get(
                                $m[1],
                                $_ENV[$m[1]] ?? isset($_SERVER[$m[1]]) ? $_SERVER[$m[1]] : null
                            );
                    }
                },
                $v
            );
            if ($type && $type != 'string') {
                switch ($type) {
                    case 'int':
                        return intval($v);
                    case 'float':
                        return floatval($v);
                    case 'bool':
                        return ($v == 'false') ? false : boolval($v);
                }
            }
        }
        if (is_array($v)) {
            array_walk($v, function (&$av) use ($type) {
                $av = $this->parseValue($av, $type);
            });
        }
        return $v;
    }
  
    private function isOrdinal($arr)
    {
        return key($arr) === 0;
    }
  
    private function parseArgs(\ReflectionFunctionAbstract $f, array $args = [])
    {
        $result = [];
        $params = $f->getParameters();
        $n1 = count($args);
        $n2 = count($params);
        $j = 0;
        for ($i = 0; $i < $n2; $i++) {
            $param = $params[$i];
            $type = $param->getType();
            if ($type && !$type->isBuiltin()) {
                $tmp = null;
                if ($type instanceof \ReflectionNamedType) {
                    $className = $type->getName();
                    if ($className === App::class) {
                        $tmp = $this->app;
                    }
                    if ($className === Config::class) {
                        $tmp = $this->app->config();
                    }
                    if ($className === Request::class) {
                        $tmp = $this->app->request();
                    }
                    if ($className === Response::class) {
                        $tmp = $this->app->response();
                    }
                
                    if (!$tmp) {
                        $pv = null;
                        if ($j < $n1) {
                            $pv = $this->parseValue($args[$j]);
                            $tmp = $this->evaluate($pv);
                        }

                        if ($tmp && ($tmp instanceof $className)) {
                            $j++;
                        } elseif ($pv !== $className) {
                            $tmp = $this->evaluate($className);
                        }
                    }
                    
                    if (!$tmp &&
                        ($type->getName() === ILog::class || $type->getName() === Log::class)) {
                        $tmp = $this->app->sysLog();
                    }
                    
                    if ($tmp === "loading") {
                        throw new ScopeException(ScopeException::CIRCULAR_DEPENDENCY);
                    }
                }
                $result[$i] = $tmp;
                $tmp = null;
            } elseif ($j < $n1) {
                $result[$i] = $this->parseValue($args[$j], $type);
                $j++;
            }
        }
        return $result;
    }
  
    private function parseOptions(\ReflectionClass $c, $options)
    {
        $result = [];
        if (is_array($options)) {
            foreach ($options as $nm => $v) {
                $m = "set" . ucwords($nm);
                if ($c->hasMethod($m)) {
                    $m1 = $c->getMethod($m);
                    if ($m1->getNumberOfParameters()) {
                        $param = $m1->getParameters()[0];
                        $type = $param->getType();
                        $tmp = (is_array($v) && $this->isOrdinal($v)) ? $v : [$v];
                        $tmp2 = [];
                        $n = count($tmp);
                        for ($i = 0; $i < $n; $i++) {
                            if (!isset($tmp[$i])) {
                                throw new ScopeException(ScopeException::INJECTION_FAILED, [$nm]);
                            }
                            $tmp2[] = ($type && !$type->isBuiltin()) ?
                                $this->evaluate($this->parseValue($tmp[$i])) :
                                $this->parseValue($tmp[$i], $type);
                        }
                        $result[$m] = $tmp2;
                    }
                }
            }
        }
        return $result;
    }
  
    private function evaluate($name)
    {
        if (isset($this->registry[$name])) {
            $v = $this->registry[$name];
            if (is_callable($v)) {
                $rf = new \ReflectionFunction($v);
                return $rf->invokeArgs($this->parseArgs($rf, []));
            }
            return $v;
        }
        if (isset($this->di[$name])) {
            if (is_string($this->di[$name])) {
                $component = $this->evaluate($this->parseValue($this->di[$name]));
                $this->registry[$name] = $component;
            } else if (is_array($this->di[$name])) {
                $cn = $this->parseValue($this->di[$name]['module']);
                $this->registry[$name] = 'loading';
                $c = new \ReflectionClass($cn);
                $component = $c->newInstanceArgs($this->parseArgs($c->getConstructor(), $this->di[$name]['args'] ?? []));
                if (!isset($this->di[$name]['singleton']) || $this->di[$name]['singleton'] == true) {
                    $this->registry[$name] = $component;
                }
                if (isset($this->di[$name]["options"])) {
                    try {
                        $opts = $this->parseOptions($c, $this->di[$name]["options"]);
                    } catch (\Exception $e) {
                        throw new ScopeException(ScopeException::COMPONENT_FAILED, [$name], $e);
                    }
                    foreach ($opts as $m => $v) {
                        if (!is_array($v)) {
                            $v = [$v];
                        }
                        foreach ($v as $v1) {
                            $c->getMethod($m)->invoke($component, $v1);
                        }
                    }
                }
            }
            return $component;
        }
        return null;
    }
    
    public function get($nm)
    {
        $v = $this->evaluate($nm);
        if (is_null($v)) {
            throw new ComponentNotFoundException([$nm]);
        }
        return $v;
    }
    
    public function has($nm): bool
    {
        return isset($this->registry[$nm]) || isset($this->di[$nm]);
    }
  
    public function __get($nm)
    {
        return $this->get($nm);
    }
    
    public function set($nm, $value): Scope
    {
        $this->registry[$nm] = $value;
        return $this;
    }
    
    public function __set($nm, $value): void
    {
        $this->set($nm, $value);
    }
    
    public function inject(array $di): Scope
    {
        foreach ($di as $nm => $conf) {
            unset($this->registry[$nm]);
        }
        $this->di = array_merge_recursive($this->di, $di);
        return $this;
    }
}
