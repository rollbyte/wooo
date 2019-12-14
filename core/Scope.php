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
    
    private $aliases = [];
    
    private function setAliases(): void
    {
        $this->aliases = [];
        foreach ($this->di as $nm => $value) {
            if (is_array($value) && isset($value['class'])) {
                $cn = $this->parseValue($value['class']);
                if (
                    $nm != $cn &&
                    !isset($this->aliases[$cn]) &&
                    !isset($this->di[$cn])
                ) {
                    $this->aliases[$cn] = $nm;
                }
            }
        }
    }
  
    public function __construct()
    {
        $args = func_get_args();
        $this->di = [];
        
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $this->di = array_merge($arg, $this->di);
            } elseif ($arg instanceof App) {
                $this->app = $arg;
            } elseif ($arg instanceof Scope) {
                $this->di = array_merge($arg->di, $this->di);
            }
        }
        
        $this->setAliases();
    }
  
    private function parseValue($v, $type = null)
    {
        if (is_string($v)) {
            $v = preg_replace_callback(
                '/\\$\\{([a-z0-9_]+)\\}/i',
                function ($m) {
                    switch ($m[1]) {
                        case 'APP_PATH':
                            return $this->app ? $this->app->appPath() : '';
                        case 'APP_BASE':
                            return $this->app ? $this->app->appBase() : '';
                        default:
                            $default = $_ENV[$m[1]] ?? isset($_SERVER[$m[1]]) ? $_SERVER[$m[1]] : null;
                            return $this->app ? $this->app->config()->get($m[1], $default) : $default;
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
                    case \DateTime::class:
                    case DateTime::class:
                    case 'date':
                        return new DateTime($v);
                }
            }
        }
        if (is_array($v)) {
            $tmp = $v;
            $v = [];
            foreach ($tmp as $nm => $value) {
                $v[$this->parseValue($nm)] = $this->parseValue($value);
            }
        }
        return $v;
    }
  
    private function isOrdinal($arr)
    {
        return key($arr) === 0;
    }
  
    private function parseArgs(\ReflectionFunctionAbstract $f, array $args, array $passed = [])
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
                    if ($this->app) {
                        if ($className === App::class) {
                            $tmp = $this->app;
                        }
                        if ($className === Config::class) {
                            $tmp = $this->app->config();
                        }
                        if ($className === Request::class) {
                            $tmp = $this->app->request();
                        }
                        if ($className === Session::class) {
                            $tmp = $this->app->request() ? $this->app->request()->session() : null;
                        }
                        if ($className === Scope::class) {
                            $tmp = $this;
                        }
                        if ($className === Response::class) {
                            $tmp = $this->app->response();
                        }
                    }
                    if (!$tmp) {
                        $pv = null;
                        if ($j < $n1) {
                            $pv = $this->parseValue($args[$j]);
                            if (is_string($pv)) {
                                $tmp = $this->evaluate($pv);
                            }
                        }

                        if ($tmp && ($tmp instanceof $className)) {
                            $j++;
                        } elseif ($pv !== $className) {
                            if (isset($this->aliases[$className])) {
                                try {
                                    $tmp = $this->evaluate($className);
                                } catch (ScopeException $e) {
                                    if (!($param->allowsNull() || $param->isOptional())) {
                                        throw $e;
                                    }
                                }
                            } elseif (!($param->allowsNull() || $param->isOptional())) {
                                if (isset($passed[$className])) {
                                    throw new ScopeException(ScopeException::CIRCULAR_DEPENDENCY);
                                }
                                $c = new \ReflectionClass($className);
                                $passed[$className] = true;
                                if ($c->isInstantiable()) {
                                    $tmp = $c->newInstanceArgs($this->parseArgs($c->getConstructor(), [], $passed));
                                }
                            }
                        }
                    }
                    
                    if (
                        !$tmp && $this->app &&
                        ($type->getName() === ILog::class || $type->getName() === Log::class)
                    ) {
                        $tmp = $this->app->sysLog();
                    }
                    
                    if ($tmp === "loading") {
                        throw new ScopeException(ScopeException::CIRCULAR_DEPENDENCY);
                    }
                }
                $result[$i] = $tmp;
                $tmp = null;
            } elseif ($j < $n1) {
                $result[$i] = $this->parseValue(
                    $args[$j],
                    ($type instanceof \ReflectionNamedType) ? $type->getName() : $type
                );
                $j++;
            }
        }
        return $result;
    }
  
    private function applyOptions($component, \ReflectionClass $c, $options)
    {
        if (is_array($options)) {
            foreach ($options as $nm => $v) {
                $m = "set" . ucwords($nm);
                if ($c->hasMethod($nm) || $c->hasMethod($m)) {
                    $m1 = $c->hasMethod($nm) ? $c->getMethod($nm) : $c->getMethod($m);
                    if ($pn = $m1->getNumberOfParameters()) {
                        $params = $m1->getParameters();
                        if (is_array($v) && $params[0]->getType() == 'array' && $pn == 1) {
                            $m1->invoke($component, $this->parseValue($v));
                        } if (is_array($v) && $this->isOrdinal($v)) {
                            $multicall = true;
                            foreach ($v as $call) {
                                if (!is_array($call) || !$this->isOrdinal($call)) {
                                    $multicall = false;
                                    break;
                                }
                            }

                            if ($multicall) {
                                $args = $this->parseArgs($m1, $v);
                                if ($pn == count(array_filter($args))) {
                                    $multicall = false;
                                }
                            }
                            
                            if ($multicall) {
                                foreach ($v as $call) {
                                    $m1->invokeArgs($component, $this->parseArgs($m1, $call));
                                }
                            } else {
                                $m1->invokeArgs($component, $args ?? $this->parseArgs($m1, $v));
                            }
                        } else {
                            $m1->invokeArgs($component, $this->parseArgs($m1, [$v]));
                        }
                    } else {
                        $m1->invoke($component);
                    }
                } else {
                    $pv = $this->parseValue($v);
                    $component->$nm = is_string($pv) ? $this->evaluate($pv) ?? $pv : $pv;
                }
            }
        }
    }
    
    private function instantiate(string $cn, ?string $name = null, array $args = [], ?array $options = null)
    {
        $c = new \ReflectionClass($cn);
        if (!$c->isInstantiable()) {
            throw new \Exception("Class $cn is not instantiable.");
        }
        if ($name) {
            $this->registry[$name] = 'loading';
        }
        $component = $c->newInstanceArgs($this->parseArgs($c->getConstructor(), $args));
        
        if ($name) {
            if (!isset($this->di[$name]['produce']) || ($this->di[$name]['produce'] !== true)) {
                $this->registry[$name] = $component;
                if ($name != $cn) {
                    $this->registry[$cn] = $component;
                }
            } else {
                unset($this->registry[$name]);
            }
        }
        
        if (is_array($options)) {
            $this->applyOptions($component, $c, $options);
        }
        return $component;
    }
  
    private function evaluate($name)
    {
        if (isset($this->registry[$name])) {
            $v = $this->registry[$name];
            if ($v === "loading") {
                throw new ScopeException(ScopeException::CIRCULAR_DEPENDENCY);
            }
            
            if (is_callable($v)) {
                $rf = new \ReflectionFunction($v);
                return $rf->invokeArgs($this->parseArgs($rf, []));
            }
            return $v;
        }
        if (isset($this->di[$name])) {
            if (is_string($this->di[$name])) {
                $this->registry[$name] = 'loading';
                $component = $this->evaluate($this->parseValue($this->di[$name]));
                $this->registry[$name] = $component;
            } elseif (is_array($this->di[$name])) {
                try {
                    $component = $this->instantiate(
                        $this->parseValue($this->di[$name]['class'] ?? $name),
                        $name,
                        $this->di[$name]['args'] ?? [],
                        $this->di[$name]["options"] ?? null
                    );
                } catch (\Exception $e) {
                    unset($this->registry[$name]);
                    throw new ScopeException(ScopeException::COMPONENT_FAILED, [$name], $e);
                }
            }
            
            return $component;
        } elseif (isset($this->aliases[$name])) {
            return $this->evaluate($this->aliases[$name]);
        }
        return null;
    }
    
    public function setApplicationContext(App $app)
    {
        $this->app = $app;
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
        return isset($this->registry[$nm]) || isset($this->di[$nm]) || isset($this->aliases[$nm]);
    }
  
    public function __get(string $nm)
    {
        return $this->get($nm);
    }
    
    public function set(string $nm, $value): Scope
    {
        $this->registry[$nm] = $value;
        return $this;
    }
    
    public function __set(string $nm, $value): void
    {
        $this->set($nm, $value);
    }
    
    public function new(string $class, array $args = [], ?array $options = null)
    {
        return $this->instantiate($class, null, $args, $options);
    }
    
    public function inject(): Scope
    {
        $scopes = func_get_args();
        foreach ($scopes as $scope) {
            $di = ($scope instanceof Scope) ? $scope->di : $scope;
            if (is_array($di)) {
                $names = array_keys($di);
                foreach ($names as $nm) {
                    unset($this->registry[$nm]);
                }
                $this->di = array_merge($this->di, $di);
            }
        }
        $this->setAliases();
        return $this;
    }
    
    public function inherit(): Scope
    {
        $scopes = func_get_args();
        foreach ($scopes as $scope) {
            $di = ($scope instanceof Scope) ? $scope->di : $scope;
            if (is_array($di)) {
                $this->di = array_merge($di, $this->di);
            }
        }
        $this->setAliases();
        return $this;
    }
}
