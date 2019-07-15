<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\stdlib\dbal\PDODriver;
use wooo\stdlib\dbal\SQLSequenceProvider;

class SQLSequenceProviderTest extends TestCase
{
    private static $connection;
    private static $driver;
    private static $provider;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        self::$connection->exec('create table seq (name text primary key, value integer)');
        self::$driver = new PDODriver(self::$connection);
        self::$provider = new SQLSequenceProvider(self::$driver);
        self::$provider->setTableName('seq');
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table seq');
    }
    
    public function testCreate(): void
    {
        self::$provider->create('seq1');
        $res = self::$connection->query('select * from seq where name = \'seq1\'');
        $seqs = $res->fetchAll();
        $res->closeCursor();
        $this->assertNotEmpty($seqs, 'sequence creation test failed');
    }
    
    /**
     * @depends testCreate
     */
    public function testNext(): void
    {
        $v = self::$provider->next('seq2');
        $this->assertEquals(1, $v, 'sequence first value getter test failed');
        $v = self::$provider->next('seq2');
        $this->assertEquals(2, $v, 'sequence next value getter test failed');
    }
}