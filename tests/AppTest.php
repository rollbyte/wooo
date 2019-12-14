<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;
use wooo\core\Scope;
use wooo\core\App;
use wooo\tests\util\ComponentMock;
use wooo\core\Request;
use wooo\core\Session;
use wooo\core\Response;
use wooo\tests\util\ComponentMock2;
use wooo\core\Log;
use wooo\core\Router;
use wooo\tests\util\ReqWrapper;
use wooo\core\DateTime;
use wooo\tests\util\ReqDataWrapper;

class AppTest extends TestCase
{
    private static $__SESSION = [];
    
    public function testConstructor(): App
    {
        $config = $this->getMockBuilder(Config::class)
        ->disableOriginalConstructor()
        ->setMethods(['set', 'get'])
        ->getMock();
        
        $config->method('get')
        ->will($this->returnCallback(function ($nm) {return $nm;}));
        
        $scope = $this->getMockBuilder(Scope::class)
        ->disableOriginalConstructor()
        ->setMethods(['get'])
        ->getMock();
        
        $app = $this->getMockBuilder(App::class)
        ->setMethods(['appPath', 'appBase', 'appRoot', 'config', 'scope', 'request', 'response', 'sysLog'])
        ->getMock();
        
        $log = new Log();
        
        $com1 = new ComponentMock('value1', $app);
        $com2 = new ComponentMock('value2', $app, $com1);
        $com3 = new ComponentMock2($app);
        
        $scope->method('get')->will($this->returnCallback(function ($nm) use ($com1, $com2, $com3) {
            switch ($nm) {
                case 'com1': return $com1;
                case 'com2': return $com2;
                case ComponentMock2::class: return $com3;
                default: return null;
            }
        }));
        
        $session = $this->getMockBuilder(Session::class)
        ->disableOriginalConstructor()
        ->setMethods(['get', 'set', 'reset', 'id'])
        ->getMock();
        
        $session->method('get')->will($this->returnCallback(function ($nm) {return self::$__SESSION[$nm];}));
        $session->method('set')->will($this->returnCallback(function ($nm, $v) {self::$__SESSION[$nm] = $v;}));
        
        $_SERVER['REQUEST_URI'] = 'http://localhost/some/path';
        
        $app->method('appPath')->will($this->returnValue('/home'));
        $app->method('appBase')->will($this->returnValue('http://localhost/'));
        $app->method('appRoot')->will($this->returnValue(''));
        $app->method('config')->will($this->returnValue($config));
        $app->method('scope')->will($this->returnValue($scope));
        $app->method('sysLog')->will($this->returnValue($log));
        
        $req = $this->getMockBuilder(Request::class)
        ->setConstructorArgs([$app])
        ->setMethods(['session', 'getMethod', 'getBody', 'getQuery', 'getFiles'])
        ->getMock();
        
        $req->http_method = 'NONE';
        $req->bodyData = new \stdClass();
        $req->queryData = new \stdClass();
        $req->fileData = new \stdClass();
        
        $req->method('session')->will($this->returnValue($session));
        $req->method('getMethod')->will($this->returnCallback(function () use ($req) {return $req->http_method;}));
        $req->method('getBody')->will($this->returnCallback(function () use ($req) {return $req->bodyData;}));
        $req->method('getQuery')->will($this->returnCallback(function () use ($req) {return $req->queryData;}));
        $req->method('getFiles')->will($this->returnCallback(function () use ($req) {return $req->fileData;}));
        
        $res = $this->getMockBuilder(Response::class)
        ->disableOriginalConstructor()
        ->setMethods(['redirect', 'render', 'send', 'setStatus', 'setCookie', 'setHeader'])
        ->getMock();
        
        $res->method('setStatus')->will($this->returnSelf());
        $res->method('setHeader')->will($this->returnSelf());
        $res->method('setCookie')->will($this->returnSelf());
        
        $app->method('request')->will($this->returnValue($req));
        $app->method('response')->will($this->returnValue($res));
        
        $this->assertTrue(true);
        return $app;
    }
    
    /**
     * @depends testConstructor
     */
    public function testUse(App $app): void
    {
        $checked = false;
        $app
        ->use(function (App $app, Request $req, ComponentMock2 $com) use (&$checked) {
            $checked = true;
        })
        ->use('/invalid/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = 'invalid';
        });
        
        $this->assertNotEquals('invalid', $checked, 'Invalid path use test failed');
        $this->assertTrue($checked, 'non-conditional use test failed');
        
        $app->use('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
            $req->http_method = 'GET';
        })
        ->use('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = 'invalid_method';
        }, 'POST');
        
        $this->assertNotEquals('invalid_method', $checked, 'Invalid method use test failed');
        $this->assertTrue($checked, 'routed use test failed');
        $checked = false;
        $app->use('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        }, 'GET');
        $this->assertTrue($checked, 'routed by method use test failed');

        $app->use('/:first(\w+)/:second', function (App $app, Request $req, Response $res) use (&$checked) {
            $this->assertEquals('some', $req->getParameters()->first, 'check explicit regexp parameter failed');
            $this->assertEquals('path', $req->getParameters()->second, 'check implicit regexp parameter failed');
        }, 'GET');
        
        $app->use('/\w+/:second', function (App $app, Request $req, Response $res) use (&$checked) {
            $this->assertEquals('path', $req->getParameters()->second, 'check of one level path subst failed');
        }, 'GET');
        
        $checked = false;
        $app->use('/', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        }, 'GET');
        $this->assertTrue($checked, 'check of multilevel path subst failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testRouter(App $app): void
    {
        $router = new Router();
        $checked = false;
        $router->use('/path', function (Request $req) use (&$checked) {
            $checked = true;
        });
        $app->use('/some', $router);
        $this->assertTrue($checked, 'check of Router using failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testGet(App $app): void
    {
        $app->request()->http_method = 'GET';
        $checked = false;
        $app->get('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        });
        $this->assertTrue($checked, 'valid GET middleware test failed');    
    }
    
    /**
     * @depends testConstructor
     */
    public function testPost(App $app): void
    {
        $app->request()->http_method = 'POST';
        $checked = false;
        $app->post('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        });
        $this->assertTrue($checked, 'valid POST middleware test failed');
    }

    /**
     * @depends testConstructor
     */
    public function testPut(App $app): void
    {
        $app->request()->http_method = 'PUT';
        $checked = false;
        $app->put('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        });
        $this->assertTrue($checked, 'valid PUT middleware test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testDelete(App $app): void
    {
        $app->request()->http_method = 'DELETE';
        $checked = false;
        $app->delete('/some/path', function (App $app, Request $req, Response $res) use (&$checked) {
            $checked = true;
        });
        $this->assertTrue($checked, 'valid DELETE middleware test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testDispatch(App $app): void
    {
        $checked = false;
        $app->dispatch([
            '/some/path' => function (App $app, Request $req, Response $res) use (&$checked) {
                $checked = true;
            }
        ])
        ->dispatch([
            '/invalid/path' => function (App $app, Request $req, Response $res) use (&$checked) {
                $checked = 'invalid';
             }
        ]);
        $this->assertNotEquals('invalid', $checked, 'invalid path dispatch test failed');
        $this->assertTrue($checked, 'basic dispatch test failed');
        $checked = false;
        $app->dispatch([
            'some' => [
                'path' => function (App $app, Request $req, Response $res) use (&$checked) {
                    $checked = true;
                },
                ':second' => function (App $app, Request $req, Response $res) use (&$checked) {
                    $this->assertEquals('path', $req->getParameters()->second, 'routed dispatch param test failed');
                }
                
            ] 
        ]);
        $this->assertNotEquals('invalid', $checked, 'invalid path dispatch test failed');
        $this->assertTrue($checked, 'routed dispatch test failed');
        
        $checked = false;
        $app->request()->http_method = 'POST';
        $app->dispatch([
            '/some/path' => [
                'handler' => function (App $app, Request $req, Response $res) use (&$checked) {
                    $checked = 'invalid_method';
                },
                'method' => 'GET'
            ]
        ])
        ->dispatch([
            '/some/path' => [
                'handler' => function (App $app, Request $req, Response $res) use (&$checked) {
                    $checked = true;
                },
                'method' => 'POST'
            ]
        ]);
        $this->assertNotEquals('invalid_method', $checked, 'invalid method dispatch test failed');
        $this->assertTrue($checked, 'method dispatch test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testNotfound(App $app): void
    {
        $app->notFound();
        $this->assertTrue(true);
    }
    
    /**
     * @depends testConstructor
     */
    public function testRequestDataWrapper(App $app): void
    {
        $app->request()->http_method = 'POST';
        $app->request()->bodyData = (object)['a' => 1];
        $app->request()->queryData = (object)['b' => 2];
        $app->request()->fileData = (object)['d' => 4];
        $checked = false;
        $app->post('/some/:c', function (ReqDataWrapper $r) use (&$checked) {
            $checked = ($r->a + $r->b + $r->d) . $r->c == '7path';
        });
        $this->assertTrue($checked, 'Request data wrapper interface test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testRequestWrapper(App $app): void
    {
        $app->request()->http_method = 'POST';
        $app->request()->bodyData = (object)['a' => 1];
        $app->request()->queryData = (object)['b' => 2];
        $app->request()->fileData = (object)['d' => 4];
        $checked = false;
        $app->post('/some/:c', function (ReqWrapper $r) use (&$checked) {
            $checked = ($r->a + $r->b + $r->d) . $r->c == '7path';
        });
        $this->assertTrue($checked, 'Request wrapper interface test failed');
    }
    
    
    /**
     * @depends testConstructor
     */
    public function testReqParamPassing(App $app): void
    {
        $app->request()->http_method = 'POST';
        $app->request()->bodyData = (object)['a' => '1', 'e' => [1, 2, 3]];
        $app->request()->queryData = (object)['b' => '2.678', 'd' => '1917-10-25'];
        $app->request()->fileData = (object)['f' => 0];
        $checkedA = false;
        $checkedB = false;
        $checkedC = false;
        $checkedD = false;
        $checkedE = false;
        $checkedF = false;
        $app->post('/some/:c', function (int $a, float $b, string $c, \DateTime $d, bool $f, $e, $ololo = 'lol', ?int $i)
            use (&$checkedA, &$checkedB, &$checkedC, &$checkedD, &$checkedE, &$checkedF) {
            $checkedA = $a === 1;
            $checkedB = $b === 2.678;
            $checkedC = $c === 'path';
            $checkedD = $d instanceof DateTime;
            $checkedE = is_array($e);
            $checkedF = $f === false;
        });
        $this->assertTrue($checkedA, 'int request param binding test failed');
        $this->assertTrue($checkedB, 'float request param binding test failed');
        $this->assertTrue($checkedC, 'string request param binding test failed');
        $this->assertTrue($checkedD, 'datetime request param binding test failed');
        $this->assertTrue($checkedE, 'untyped request param binding test failed');
        $this->assertTrue($checkedF, 'bool request param binding test failed');
    }
}
