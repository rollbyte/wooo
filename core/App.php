<?php

namespace wooo\core;

use wooo\core\exceptions\NotFoundException;
use wooo\core\exceptions\AccessDeniedException;
use wooo\core\exceptions\InternalException;
use wooo\core\exceptions\CoreException;

class App
{
    /**
     * @var \wooo\core\Config
     */
    private $config;
  
    /**
     * @var \wooo\core\Scope
     */
    private $scope;
  
    /**
     * @var \wooo\core\Request
     */
    private $req;
  
    /**
     * @var \wooo\core\Response
     */
    private $res;
  
    private $appPath;
  
    private $appRoot;
  
    private $appBase;
  
    /**
     * @var \wooo\core\ILog
     */
    private $log;
    
    private static $HTTP_METHODS = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'SEARCH', 'HEAD'];
    
    private $rw;
  
    public function scope(): Scope
    {
        return $this->scope;
    }
    
    public function config(): Config
    {
        return $this->config;
    }
    
    public function request(): Request
    {
        return $this->req;
    }
  
    public function response(): Response
    {
        return $this->res;
    }
    
    public function appPath(): string
    {
        return $this->appPath;
    }
  
    public function appRoot(): string
    {
        return $this->appRoot;
    }
  
    public function appBase(): string
    {
        return $this->appBase;
    }
    
    private function acceptCArg($arg, &$configSet)
    {
        if (is_string($arg)) {
            $this->appPath = $arg;
        } elseif (is_array($arg)) {
            if (!$configSet) {
                $this->config->merge($arg);
                $configSet = true;
            } else {
                $this->scope->inject($arg);
            }
        } elseif ($arg instanceof Config) {
            $this->config->merge($arg);
        } elseif ($arg instanceof Scope) {
            $this->scope->inject($arg);
        } elseif ($arg instanceof Request) {
            $this->req = $arg->forContext($this);
        } elseif ($arg instanceof Response) {
            $this->res = $arg;
        } elseif ($arg instanceof App) {
            $this->config->merge($arg->config());
            $this->scope->inherit($arg->scope());
            if (!$this->req) {
                $this->req = $arg->request()->forContext($this);
            }
            if (!$this->res) {
                $this->res = $arg->response();
            }
        }
    }
  
    public function __construct()
    {
        $this->appPath = getcwd();
        
        $this->scope = new Scope($this);
        $this->config = new Config();
        
        $args = func_get_args();
        $configSet = false;
        foreach ($args as $arg) {
            $this->acceptCArg($arg, $configSet);
        }
        
        $this->appRoot = '';
        $this->appBase = $this->appPath;
        
        $isWeb = false;
        
        if (isset($_SERVER['HTTP_HOST'])) {
            $isWeb = true;
            $this->appRoot = $this->config->get(
                'appRootPath',
                str_replace($_SERVER['DOCUMENT_ROOT'], '', $this->appPath)
            );
            $host = $_SERVER['HTTP_HOST'];
            $schema = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $this->appBase = $this->config->get('appBaseURL', "$schema://$host$this->appRoot");
        }
        
        $INCLUDE_PATH = get_include_path() . PATH_SEPARATOR . $this->appPath;
    
        $PATH = $this->config->get('PATH');
        if ($PATH) {
            if (is_array($PATH)) {
                foreach ($PATH as $P) {
                    $INCLUDE_PATH = $INCLUDE_PATH . PATH_SEPARATOR . $P;
                }
            } else {
                $INCLUDE_PATH = $INCLUDE_PATH . PATH_SEPARATOR . $PATH;
            }
        }
        set_include_path($INCLUDE_PATH);
        $this->log = new Log();
        if ($isWeb) {
            if (!$this->req) {
                $this->req = new Request($this);
            }
            if (!$this->res) {
                $this->res = new Response($this);
            }
        }
    }
  
    public function log(ILog $log): App
    {
        $this->log = $log;
        return $this;
    }
  
    public function sysLog(): ILog
    {
        return $this->log;
    }
    
    public function __get($nm)
    {
        return $this->scope()->get($nm);
    }
    
    private function logError(\Throwable $e)
    {
        if ($e->getPrevious()) {
            $this->logError($e->getPrevious());
        }
        $this->sysLog()->error($e);
    }
  
    private function run($module)
    {
        try {
            $reflection = new \ReflectionFunction($module);
            $params = $reflection->getParameters();
            $args = [];
            /**
             * @var $param \ReflectionParameter
             */
            foreach ($params as $param) {
                $type = $param->getType();
                $name = $param->getName();
                if ($type && !$type->isBuiltin()) {
                    if ($type instanceof \ReflectionNamedType) {
                        switch ($type->getName()) {
                            case App::class:
                                $tmp = $this;
                                break;
                            case Config::class:
                                $tmp = $this->config();
                                break;
                            case Scope::class:
                                $tmp = $this->scope();
                                break;
                            case Request::class:
                                $tmp = $this->request();
                                break;
                            case Response::class:
                                $tmp = $this->response();
                                break;
                            case Session::class:
                                if ($this->request()) {
                                    $tmp = $this->request()->session();
                                }
                                break;
                            case \DateTime::class:
                            case DateTime::class:
                                if (isset($this->request()->$name)) {
                                    $tmp = new DateTime($this->request()->$name);
                                }
                                break;
                            default:
                                if (is_subclass_of($type->getName(), IRequestDataWrapper::class)) {
                                    $rc = new \ReflectionClass($type->getName());
                                    $tmp = $rc->newInstance(
                                        (object)array_merge(
                                            get_object_vars($this->request()->getBody()),
                                            get_object_vars($this->request()->getParameters()),
                                            get_object_vars($this->request()->getQuery()),
                                            get_object_vars($this->request()->getFiles()),
                                        )
                                    );
                                } elseif (is_subclass_of($type->getName(), IRequestWrapper::class)) {
                                    $rc = new \ReflectionClass($type->getName());
                                    $tmp = $rc->newInstance($this->request());
                                } else {
                                    $tmp = $this->scope()->get($type->getName());
                                }
                                break;
                        }
                    }
                } elseif (isset($this->request()->$name)) {
                    $tmp = $this->request()->$name;
                    if ($type) {
                        if ($type instanceof \ReflectionNamedType) {
                            switch ($type->getName()) {
                                case 'string':
                                    $tmp = strval($tmp);
                                    break;
                                case 'int':
                                    $tmp = intval($tmp);
                                    break;
                                case 'float':
                                    $tmp = floatval($tmp);
                                    break;
                                case 'bool':
                                    $tmp = boolval($tmp);
                                    break;
                            }
                        }
                    }
                }
                if (!isset($tmp) && !$param->isDefaultValueAvailable() && !$param->allowsNull()) {
                    throw new CoreException(CoreException::INVALID_HANDLER_ARGUMENT, [$name]);
                }
                $args[] = $tmp ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
                $tmp = null;
            }
            $result = $reflection->invokeArgs($args);
            if ($this->response()->isSendable($result)) {
                $this->response()->send($result);
            }
        } catch (NotFoundException $e) {
            $this->response()->setStatus(404)->send($e->getMessage());
        } catch (AccessDeniedException $e) {
            $this->response()->setStatus(403)->send($e->getMessage());
        } catch (InternalException $e) {
            $this->logError($e->getPrevious());
            $this->response()->setStatus(500)->send($e->getMessage());
        } catch (\Throwable $e) {
            $this->logError($e);
            $this->response()->setStatus(500)->send($e->getMessage());
        }
    }
    
    private function getRouterWrapper()
    {
        if (!$this->rw) {
            $this->rw = new class extends Router
            {
                public function __map(Router $r): array
                {
                    return $r->map;
                }
            };
        }
        return $this->rw;
    }
    
    private function runRouter(string $context, Router $r)
    {
        $map = $this->getRouterWrapper()->__map($r);
        foreach ($map as $args) {
            $paths = [];
            $methods = [];
            $handlers = [];
            $this->readUseArgs($args, $paths, $methods, $handlers, $context);
            call_user_func_array([$this, 'use'], array_merge($paths, $methods, $handlers));
        }
    }
    
    private function readUseArgs(array $src, array &$paths, array &$methods, array &$handlers, $context = ''): void
    {
        foreach ($src as $arg) {
            if (is_string($arg)) {
                if (in_array(strtoupper($arg), self::$HTTP_METHODS, true)) {
                    $methods[] = strtoupper($arg);
                } elseif (!$arg || $arg[0] == '/') {
                    $paths[] = $context . $arg;
                } else {
                    $handlers[] = $arg;
                }
            } elseif (is_array($arg)) {
                $this->readUseArgs($arg, $paths, $methods, $handlers, $context);
            } elseif (is_callable($arg) || ($arg instanceof Router)) {
                $handlers[] = $arg;
            }
        }
    }
    
  
    public function use($arg0): App
    {
        $paths = [];
        $methods = [];
        $modules = [];
        $args = func_get_args();
        
        $this->readUseArgs($args, $paths, $methods, $modules);

        if (!empty($paths)) {
            $fits = false;
            foreach ($paths as $pth) {
                if ($this->request()->checkPath($pth)) {
                    $fits = true;
                    $this->request()->parsePath($pth);
                }
            }
            if (!$fits) {
                return $this;
            }
        }
    
        if (!empty($methods)) {
            if (!in_array($this->request()->getMethod(), $methods)) {
                return $this;
            }
        }
    
        foreach ($modules as $module) {
            if (is_string($module)) {
                $module = include $module;
            }
            if ($module instanceof Router) {
                if (empty($paths)) {
                    $this->runRouter('', $module);
                } else {
                    foreach ($paths as $pth) {
                        $this->runRouter($pth, $module);
                    }
                }
            } else {
                if (!is_callable($module)) {
                    $this->sysLog()->error(new InternalException('Invalid middleware injected.'));
                    $this->response()->setStatus(500)->send('Internal server error!');
                }
                $this->run($module);
            }
        }
    
        return $this;
    }
    
    private function iterateMap($map, $prefix = '')
    {
        foreach ($map as $path => $handler) {
            $method = null;
            if (is_array($handler)) {
                $method = isset($handler["method"]) ? $handler["method"] : null;
                $handler = isset($handler["handler"]) ? $handler["handler"] : null;
            }
            if ($handler) {
                $path = ($prefix ? $prefix . '/' : '') . $path;
                if ($path) {
                    $this->use($path, $handler, $method);
                } else {
                    $this->use($handler, null, $method);
                }
            }
        }
    }
    
    private function route($map, $i, $path)
    {
        if (isset($map[$path[$i]])) {
            $h = $map[$path[$i]];
            if (is_array($h) && !is_callable($h)) {
                $this->route($map[$path[$i]], $i + 1, $path);
                $method = isset($h["method"]) ? $h["method"] : null;
                $handler = isset($h["handler"]) ? $h["handler"] : null;
                if ($handler) {
                    if ($i == count($path) - 1) {
                        $this->use(join('/', array_slice($path, 0, $i)), $handler, $method);
                    }
                }
            } elseif ((is_string($h) || is_callable($h)) && ($i == count($path) - 1)) {
                $this->use(join('/', array_slice($path, 0, $i)), $h);
            }
        }
        $this->iterateMap($map, join('/', array_slice($path, 0, $i)));
    }
  
    public function dispatch(array $map): App
    {
        $path = explode('/', $this->request()->getPath());
        $this->route($map, 1, $path);
        return $this;
    }
    
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
  
    public function get($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['GET'],
                $this->forceMethod('GET', func_get_args())
            )
        );
    }
  
    public function post($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['POST'],
                $this->forceMethod('POST', func_get_args())
            )
        );
    }
  
    public function delete($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['DELETE'],
                $this->forceMethod('DELETE', func_get_args())
            )
        );
    }
  
    public function put($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['PUT'],
                $this->forceMethod('PUT', func_get_args())
            )
        );
    }
    
    public function patch($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['PATCH'],
                $this->forceMethod('PATCH', func_get_args())
            )
        );
    }
    
    public function head($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['HEAD'],
                $this->forceMethod('HEAD', func_get_args())
            )
        );
    }
    
    public function search($arg0): App
    {
        return call_user_func_array(
            [$this, 'use'],
            array_merge(
                ['SEARCH'],
                $this->forceMethod('SEARCH', func_get_args())
            )
        );
    }
    
    public function notFound()
    {
        $this->response()->setStatus(404)->send("Resource not found!");
    }
}
