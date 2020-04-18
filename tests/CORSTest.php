<?php

namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;
use wooo\core\Scope;
use wooo\core\App;
use wooo\core\Request;
use wooo\core\Session;
use wooo\core\Response;
use wooo\core\RequestData;
use wooo\core\HttpMethod;
use wooo\tests\util\NonExitableApp;
use wooo\core\ILog;
use wooo\lib\middleware\CSRF;
use wooo\core\Token;
use wooo\lib\middleware\CORS;

class CORSTest extends TestCase
{
    private static $__CONFIG = [
      'CORS' => ['http://www.google.com']
    ];
    
    private static $__HEADERS = [];
    
    private function init(): App
    {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->setMethods(['set', 'get'])
            ->getMock();
        
        $config->method('get')
            ->will($this->returnCallback(function ($nm, $def = null) {
                return self::$__CONFIG[$nm] ?? $def;
            }));
        
        /**
         * @var App
         */
        $app = $this->getMockBuilder(NonExitableApp::class)
            ->setMethods(['appPath', 'appBase', 'appRoot', 'config', 'scope', 'request', 'response', 'sysLog'])
            ->getMock();
        
        $log = new class () implements ILog {
            public function warn(string $msg): void
            {
            }
            
            public function error(\Throwable $error): void
            {
            }
            
            public function info(string $msg): void
            {
            }
        };
        
        $app->log($log);
        
        $_SERVER['REQUEST_URI'] = 'http://localhost/some/path';
        
        $app->method('appPath')->will($this->returnValue('/home'));
        $app->method('appBase')->will($this->returnValue('http://localhost/'));
        $app->method('appRoot')->will($this->returnValue(''));
        $app->method('config')->will($this->returnValue($config));
        $app->method('sysLog')->will($this->returnValue($log));
        
        $req = $this->getMockBuilder(Request::class)
            ->setConstructorArgs([$app])
            ->setMethods(['getMethod'])
            ->getMock();
        
        $req->http_method = 'NONE';
        
        $req->method('getMethod')->will($this->returnCallback(function () use ($req) {
            return $req->http_method;
        }));
        
        $res = $this->getMockBuilder(Response::class)
            ->setConstructorArgs([$app])
            ->setMethods(['redirect', 'render', 'send', 'setStatus', 'setHeader'])
            ->getMock();
        
        $res->http_status = 200;
        $res->method('setStatus')->will($this->returnCallback(function ($s) use ($res) {
            $res->http_status = $s;
            return $res;
        }));
        $res->method('setHeader')->will($this->returnCallback(function ($h) use ($res) {
            self::$__HEADERS[] = $h;
            return $res;
        }));
        $res->method('send')->will($this->returnCallback(function ($v) use ($app) {
            $app->exit();
        }));
        
        $app->method('request')->will($this->returnValue($req));
        $app->method('response')->will($this->returnValue($res));
        
        return $app;
    }
    
    public function testFail(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::GET;
        $_SERVER['HTTP_ORIGIN'] = 'http://www.wooo.dev';
        $app->use(CORS::handler());
        $this->assertEquals(403, $app->response()->http_status);
    }
    
    public function testSuccess(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::OPTIONS;
        $_SERVER['HTTP_ORIGIN'] = 'http://www.google.com';
        $app->use(CORS::handler());
        $this->assertEquals(200, $app->response()->http_status);
            
        $app->request()->http_method = HttpMethod::POST;
        $_SERVER['HTTP_ORIGIN'] = 'http://www.wooo.dev';
        $app->use(CORS::handler(['http://www.wooo.dev']));
        $this->assertEquals(200, $app->response()->http_status);
    }
}
