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
  
    public function __construct($appPath, $config = [], $di = [])
    {
        $this->appPath = $appPath;
        $this->config = new Config($config);
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
        $INCLUDE_PATH = get_include_path() . PATH_SEPARATOR .
                        realpath(__DIR__ . '/../..') .
                        PATH_SEPARATOR . $appPath;
    
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
        $this->scope = new Scope($this, $di);
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
  
    public function use($arg0, $arg1 = null, $arg2 = null): App
    {
        $path = $arg0;
        $module = $arg1;
        $method = $arg2;
        if (!$module) {
            $module = $path;
            $path = null;
        }
    
        if ($path) {
            if (!$this->request()->checkPath($path)) {
                return $this;
            }
            $this->request()->parsePath($path);
        }
    
        if ($method) {
            if ($this->request()->getMethod() != $method) {
                return $this;
            }
        }
    
        if ($module) {
            if (is_string($module)) {
                $module = include $module;
            }
            if (!is_callable($module)) {
                if ($path) {
                    $this->sysLog()->warn('Invalid handler set for path ' . $path);
                    $this->response()->setStatus(404)->send('Resource not found!');
                } else {
                    $this->sysLog()->error(new InternalException('Invalid middleware injected.'));
                    $this->response()->setStatus(500)->send('Internal server error!');
                }
            }
            $this->run($module);
        }
    
        return $this;
    }
  
    public function dispatch(array $map): App
    {
        foreach ($map as $path => $handler) {
            $method = null;
            if (is_array($handler)) {
                $method = isset($handler["method"]) ? $handler["method"] : null;
                $handler = isset($handler["handler"]) ? $handler["handler"] : null;
            }
            if ($handler) {
                if ($path) {
                    $this->use($path, $handler, $method);
                } else {
                    $this->use($handler, null, $method);
                }
            }
        }
        return $this;
    }
  
    public function get($arg0, $arg1 = null): App
    {
        return $this->use($arg0, $arg1, 'GET');
    }
  
    public function post($arg0, $arg1 = null): App
    {
        return $this->use($arg0, $arg1, 'POST');
    }
  
    public function delete($arg0, $arg1 = null): App
    {
        return $this->use($arg0, $arg1, 'DELETE');
    }
  
    public function put($arg0, $arg1 = null): App
    {
        return $this->use($arg0, $arg1, 'PUT');
    }
    
    public function notFound()
    {
        $this->response()->setStatus(404)->send("Resource not found!");
    }
}
