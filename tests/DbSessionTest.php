<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\stdlib\dbal\PDODriver;
use wooo\stdlib\session\DbSession;

class DbSessionTest extends TestCase
{
    private static $connection;
    private static $driver;
    private static $session;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        self::$connection->exec('create table session_data (id primary key, created datetime default CURRENT_TIMESTAMP, data)');
        self::$driver = new PDODriver(self::$connection);
        self::$session = new DbSession(self::$driver);
        self::$session->setTableName('session_data');
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table session_data');
    }
    
    public function testWrite(): void
    {
        $id = '1111';
        self::$session->write($id, 'session data');
        $fetcher = self::$connection->prepare('select * from session_data where id = ?');
        $fetcher->bindParam(1, $id);
        $fetcher->execute();
        $sess = $fetcher->fetchObject();
        $fetcher->closeCursor();
        $this->assertEquals('session data', $sess->data, 'session write test failed');
        
    }
    
    /**
     * @depends testWrite
     */
    public function testRead(): void
    {
        $sessd = self::$session->read('1111');
        $this->assertEquals('session data', $sessd, 'session read test failed');
    }
    
    /**
     * @depends testRead
     */
    public function testDestroy(): void
    {
        $id = '1111';
        self::$session->destroy($id);
        $fetcher = self::$connection->prepare('select * from session_data where id = ?');
        $fetcher->bindParam(1, $id);
        $fetcher->execute();
        $sess = $fetcher->fetchObject();
        $fetcher->closeCursor();
        $this->assertEmpty($sess, 'session destory test failed');
    }
    
    /**
     * @depends testDestroy
     */
    public function testGc(): void
    {
        self::$session->write('2222', 'session 2 data');
        self::$session->gc(-25000);
        
        $res = self::$connection->query('select * from session_data');
        $sess = $res->fetchAll();
        $res->closeCursor();
        $this->assertEmpty($sess, 'gc test failed');
    }
}