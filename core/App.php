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
  
    private $appBasePath;
  
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
        return $this->appBasePath;
    }
    
    private function acceptCArg($arg) {
        if (is_string($arg)) {
            $this->appPath = $arg;
        } else if (is_array($arg)) {
            if (!$this->config) {
                $this->config = new Config($arg);
            } else if (!$this->scope) {
                $this->scope = new Scope($arg, $this);
            }
        } else if ($arg instanceof Config) {
            $this->config = $arg;
        } else if ($arg instanceof Scope) {
            $this->scope = $arg;
            $this->scope->setApplicationContext($this);
        }
    }
  
    public function __construct($arg0 = null, $arg1 = null, $arg2 = null)
    {
        $this->appPath = getcwd();
        
        $this->acceptCArg($arg0 ?? null);
        $this->acceptCArg($arg1 ?? []);
        $this->acceptCArg($arg2 ?? []);
        
        $this->appRoot = '';
        $this->appBasePath = $appPath;
        
        $isWeb = false;
        
        if (isset($_SERVER['HTTP_HOST'])) {
            $isWeb = true;
            $this->appRoot = $this->config->get('appRootPath', str_replace($_SERVER['DOCUMENT_ROOT'], '', $appPath));
            $host = $_SERVER['HTTP_HOST'];
            $schema = isset($_SERVER['HTTPS']) ? 'https' : 'http';
            $this->appBasePath = "$schema://$host$this->appRoot";
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
            $this->req = new Request($this);
            $this->res = new Response($this);
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
            foreach ($params as $param) {
                $type = $param->getType();
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
                            default:
                                $tmp = $this->scope()->get($type->getName());
                                break;
                        }
                    }
                }
                if (!isset($tmp)) {
                    throw new CoreException(CoreException::INVALID_HANDLER_ARGUMENT);
                }
                $args[] = $tmp;
                $tmp = null;
            }
            $reflection->invokeArgs($args);
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
    
    private function runRouter(string $context, Router $r) {
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
        foreach ($src as $i => $arg) {
            if (is_string($arg)) {
                if (in_array(strtoupper($arg), self::$HTTP_METHODS, true)) {
                    $methods[] = strtoupper($arg);
                } else if (!$arg || $arg[0] == '/') {
                    $paths[] = $context . $arg;
                } else {
                    $handlers[] = $arg;
                }
            } else if (is_array($arg)) {
                $this->readUseArgs($arg, $paths, $methods, $handlers, $context);
            } else if (is_callable($arg) || ($arg instanceof Router)) {
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
    
        $path = null;
        if (!empty($paths)) {
            $fits = false;
            foreach ($paths as $pth) {
                if ($this->request()->checkPath($pth)) {
                    $fits = true;
                    $path = $pth;
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
                $this->runRouter($path ?? '', $module);
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
                $method = isset($handler["method"]) ? $handler["method"] : null;
                $handler = isset($handler["handler"]) ? $handler["handler"] : null;
                if ($handler) {
                    if ($i == count($path) - 1) {
                        $this->use(join('/', array_slice($path, 0, $i)), $h, $method);
                    }
                }
            } else if ((is_string($h) || is_callable($h)) && ($i == count($path) - 1)) {
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
            function ($arg) use ($method) {return !is_string($arg) || (strtoupper($arg) != $method);}
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
