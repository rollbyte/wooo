<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;
use wooo\core\Scope;
use wooo\core\App;
use wooo\core\Request;
use wooo\core\Session;
use wooo\core\Response;
use wooo\core\DateTime;
use wooo\core\RequestData;
use wooo\lib\transactions\TransactionManager;
use wooo\lib\dbal\PDODriver;
use wooo\lib\middleware\GlobalTransaction;
use wooo\core\HttpMethod;
use wooo\tests\util\NonExitableApp;
use wooo\core\ILog;

class GlobalTransactionTest extends TestCase
{
    private static $__SESSION = [];
    
    private static $connection;
    private static $driver;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        try {
            self::$connection->exec('create table test_data (id integer primary key autoincrement, name, description, created datetime default CURRENT_TIMESTAMP, due datetime)');
            self::$connection->commit();
        } catch (\Exception $e) {
            self::$connection->rollBack();
            throw $e;
        }
        self::$driver = new PDODriver(self::$connection);
        self::$driver->setDateTimeZone('UTC');
        self::$driver->setDateTimeFormat('Y-m-d H:i:s');    
    }
    
    private function init(): App
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
        
        $app = $this->getMockBuilder(NonExitableApp::class)
        ->setMethods(['appPath', 'appBase', 'appRoot', 'config', 'scope', 'request', 'response', 'sysLog'])
        ->getMock();
        
        $log = new class() implements ILog {
            public function warn(string $msg): void
            {}
        
            public function error(\Throwable $error): void
            {}
        
            public function info(string $msg): void
            {}
        };
        
        $app->log($log);
        
        $tm = new TransactionManager($log);
        $tm->manage = self::$driver;
        
        $scope->method('get')->will($this->returnCallback(function ($nm) use ($tm) {
            switch ($nm) {
                case PDODriver::class: return self::$driver;
                case TransactionManager::class: return $tm;
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
        $req->bodyData = new RequestData();
        $req->queryData = new RequestData();
        $req->fileData = new RequestData();
        
        $req->method('session')->will($this->returnValue($session));
        $req->method('getMethod')->will($this->returnCallback(function () use ($req) {return $req->http_method;}));
        $req->method('getBody')->will($this->returnCallback(function () use ($req) {return $req->bodyData;}));
        $req->method('getQuery')->will($this->returnCallback(function () use ($req) {return $req->queryData;}));
        $req->method('getFiles')->will($this->returnCallback(function () use ($req) {return $req->fileData;}));
        
        $res = $this->getMockBuilder(Response::class)
            ->setConstructorArgs([$app])
            ->setMethods(['redirect', 'render', 'send', 'setStatus', 'setCookie', 'setHeader'])
            ->getMock();
 
        $res->method('setStatus')->will($this->returnSelf());
        $res->method('setHeader')->will($this->returnSelf());
        $res->method('setCookie')->will($this->returnSelf());
        $res->method('send')->will($this->returnCallback(function ($v) use ($app) {$app->exit();}));
        
        $app->method('request')->will($this->returnValue($req));
        $app->method('response')->will($this->returnValue($res));

        return $app;
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table test_data');
    }
    
    public function testCommit(): void
    {
        $app = $this->init();
        $id = null;
        $intran = false;
        $app->use(GlobalTransaction::handler());
        $app->request()->http_method = HttpMethod::POST;
        $app->use(function (PDODriver $d, TransactionManager $tm, Response $res) use (&$id, &$intran) {
            $intran = $tm->inTransaction();
            $due = new \DateTime('20291002');
            $output = [];
            $d->execute(
                'insert into test_data (name, description, due) values (:n,:d,:due)',
                [
                    'n' => 'first',
                    'd' => 'this is the first object',
                    'due' => $due->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                ],
                $output
            );
            $id = $output['rowid'];
            $res->send('ok');
        });
            
        $this->assertTrue($intran, 'global transaction start on POST test failed');
        $td = self::$driver->get('select * from test_data');
        $this->assertNotNull($td, 'global transaction commit on POST test failed');
        
        $intran = false;
        $app->request()->http_method = HttpMethod::PUT;
        $app->use(function (PDODriver $d, TransactionManager $tm, Response $res) use ($id, &$intran) {
            $intran = $tm->inTransaction();
            $d->execute(
                'update test_data set name = :n where id = :id',
                [
                    'n' => 'first put',
                    'id' => $id
                ]
            );
            $res->send('ok');
        });
        
        $this->assertTrue($intran, 'global transaction start on PUT test failed');    
        $td = self::$driver->get('select * from test_data');
        $this->assertEquals('first put', $td->name, 'global transaction commit on PUT test failed');
        
        $intran = false;
        $app->request()->http_method = HttpMethod::PATCH;
        $app->use(function (PDODriver $d, TransactionManager $tm, Response $res) use ($id, &$intran) {
            $intran = $tm->inTransaction();
            $d->execute(
                'update test_data set name = :n where id = :id',
                [
                    'n' => 'first patch',
                    'id' => $id
                ]
            );
            $res->send('ok');
        });
            
        $this->assertTrue($intran, 'global transaction start on PATCH test failed');
        $td = self::$driver->get('select * from test_data');
        $this->assertEquals('first patch', $td->name, 'global transaction commit on PATCH test failed');
        
        $intran = false;
        $app->request()->http_method = HttpMethod::DELETE;
        $app->use(function (PDODriver $d, TransactionManager $tm, Response $res) use ($id, &$intran) {
            $intran = $tm->inTransaction();
            $d->execute('delete from test_data where id = :id', ['id' => $id]);
            $res->send('ok');
        });
        
        $this->assertTrue($intran, 'global transaction start on DELETE test failed');
        $td = self::$driver->get('select * from test_data');
        $this->assertNull($td, 'global transaction commit on DELETE test failed');  
    }
    
    public function testNoTransaction(): void
    {
        $app = $this->init();
        $app->use(GlobalTransaction::handler());
        
        $intran = true;
        $app->request()->http_method = HttpMethod::GET;
        $app->use(function (TransactionManager $tm, Response $res) use (&$intran) {
            $intran = $tm->inTransaction();
            $res->send('ok');
        });
        $this->assertFalse($intran, 'global transaction no start on GET test failed');

        $intran = true;
        $app->request()->http_method = HttpMethod::HEAD;
        $app->use(function (TransactionManager $tm, Response $res) use (&$intran) {
            $intran = $tm->inTransaction();
            $res->send('ok');
        });
        $this->assertFalse($intran, 'global transaction no start on HEAD test failed');
        
        $intran = true;
        $app->request()->http_method = HttpMethod::SEARCH;
        $app->use(function (TransactionManager $tm, Response $res) use (&$intran) {
            $intran = $tm->inTransaction();
            $res->send('ok');
        });
        $this->assertFalse($intran, 'global transaction no start on SEARCH test failed');    
    }
    
    public function testRollBack(): void
    {
        $app = $this->init();
        $id = null;
        $intran = false;
        $app->use(GlobalTransaction::handler());
        $app->request()->http_method = HttpMethod::POST;
        $app->use(function (PDODriver $d, TransactionManager $tm, Response $res) use (&$id, &$intran) {
            $intran = $tm->inTransaction();
            $due = new \DateTime('20291005');
            $output = [];
            $d->execute(
                'insert into test_data (name, description, due) values (:n,:d,:due)',
                [
                    'n' => 'second',
                    'd' => 'this is the second object',
                    'due' => $due->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                ],
                $output
                );
            $id = $output['rowid'];
            throw new \Exception('Just fail!');
            $res->send('ok');
        });
            
        $this->assertTrue($intran, 'global transaction start on POST test failed');
        $td = self::$driver->get('select * from test_data where id = :id', ['id' => $id]);
        $this->assertNull($td, 'global transaction rollback on exception test failed');
    }
}
