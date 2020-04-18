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

class CSRFTest extends TestCase
{
    private static $__SESSION = [];
    
    private static $__CONFIG = [];
    
    private static $__HEADERS = [];
    
    private static $__COOKIES = [];
    
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
        
        $scope = $this->getMockBuilder(Scope::class)
            ->disableOriginalConstructor()
            ->setMethods(['get'])
            ->getMock();
        
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
        
        $scope->method('get')->will($this->returnValue(null));
            
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set', 'reset', 'id'])
            ->getMock();
        
        $session->method('get')->will($this->returnCallback(function ($nm) {
            return self::$__SESSION[$nm] ?? null;
        }));
        $session->method('set')->will($this->returnCallback(function ($nm, $v) use ($session) {
            self::$__SESSION[$nm] = $v;
            return $session;
        }));
        
        $_SERVER['REQUEST_URI'] = 'http://localhost/some/path';
        
        $app->method('appPath')->will($this->returnValue('/home'));
        $app->method('appBase')->will($this->returnValue('http://localhost/'));
        $app->method('appRoot')->will($this->returnValue(''));
        $app->method('config')->will($this->returnValue($config));
        $app->method('scope')->will($this->returnValue($scope));
        $app->method('sysLog')->will($this->returnValue($log));
        
        $req = $this->getMockBuilder(Request::class)
            ->setConstructorArgs([$app])
            ->setMethods(['session', 'getMethod', 'getBody'])
            ->getMock();
        
        $req->http_method = 'NONE';
        
        $body = new RequestData();
        
        $req->method('session')->will($this->returnValue($session));
        $req->method('getMethod')->will($this->returnCallback(function () use ($req) {
            return $req->http_method;
        }));
        $req->method('getBody')->will($this->returnCallback(function () use ($body) {
            return $body;
        }));
        
        $res = $this->getMockBuilder(Response::class)
            ->setConstructorArgs([$app])
            ->setMethods(['redirect', 'render', 'send', 'setStatus', 'setCookie', 'setHeader'])
            ->getMock();
        
        $res->http_status = 200;
        $res->http_data = null;
        $res->method('setStatus')->will($this->returnCallback(function ($s) use ($res) {
            $res->http_status = $s;
            return $res;
        }));
        $res->method('setHeader')->will($this->returnCallback(function ($h) use ($res) {
            self::$__HEADERS[] = $h;
            return $res;
        }));
        $res->method('setCookie')->will($this->returnCallback(function ($nm, $v) use ($res) {
            self::$__COOKIES[$nm] = $v;
            return $res;
        }));
        $res->method('send')->will($this->returnCallback(function ($v) use ($app, $res) {
            $res->http_data = $v;
            $app->exit();
        }));
        
        $app->method('request')->will($this->returnValue($req));
        $app->method('response')->will($this->returnValue($res));
        
        return $app;
    }

    public function testTokenSessSetting(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::GET;
        $app->use(CSRF::handler('csrf_token'));
        
        $token = null;
        $app->use(function (App $app) use (&$token) {
            $token = $app->request()->session()->get('csrf_token');
        });
        $this->assertNotNull($token, 'csrf sess setting check failed');

        $token = new Token($token, false);
        //$this->assertEquals('csrf-token-value: ' . $b64, $header, 'csrf header value check failed');
        $resp_token = new Token(base64_decode($app->response()->csrf_token_value));
        $this->assertEquals($token->value(), $resp_token->value(), 'csrf response variable value check failed');

        $h = explode(':', self::$__HEADERS[1]);
        $resp_token = new Token(base64_decode(trim($h[1])));
        $this->assertEquals($token->value(), $resp_token->value(), 'csrf response header value check failed');
    }
    
    public function testFail(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::GET;
        $app->use(CSRF::handler('csrf_token'));
        $app->request()->http_method = HttpMethod::POST;
        $app->use(CSRF::handler('csrf_token'));
        $this->assertEquals(403, $app->response()->http_status);
    }
    
    public function testSuccess(): void
    {
        $app = $this->init();
        $app->request()->http_method = HttpMethod::GET;
        $app->use(CSRF::handler('csrf_token'));
        $token = $app->response()->csrf_token_value;
            
        $app->request()->http_method = HttpMethod::POST;
        $app->request()->getBody()['csrf_token'] = $token;
        
        $app->use(CSRF::handler('csrf_token'));
        $this->assertEquals(200, $app->response()->http_status);
    }
}
