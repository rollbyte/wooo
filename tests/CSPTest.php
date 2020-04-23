<?php

namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;
use wooo\core\App;
use wooo\core\Request;
use wooo\core\Response;
use wooo\core\HttpMethod;
use wooo\tests\util\NonExitableApp;
use wooo\core\Log;
use wooo\lib\middleware\CSP;

class CSPTest extends TestCase
{
    private static $__CONFIG = [
      'CSP' => ['script-src' => ['a']]
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
        
        $_SERVER['REQUEST_URI'] = 'http://localhost/some/path';

        $log = new Log();
        
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
    
    public function testSuccess(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::OPTIONS;
        $_SERVER['HTTP_ORIGIN'] = 'http://www.google.com';
        $app->use(CSP::handler(['script-src' => ['b']]));
        $this->assertEquals(
            "Content-Security-Policy: default-src 'self'; script-src a b",
            self::$__HEADERS[0],
            'CSP header check failed'
        );
    }
}
