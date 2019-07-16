<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\lib\dbal\PDODriver;
use wooo\lib\dbal\interfaces\DbDriver;

class PDODriverTest extends TestCase
{
    private static $connection;
    private static $driver;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        try {
            self::$connection->exec('create table test_data (id integer primary key autoincrement, name, description, created datetime default CURRENT_TIMESTAMP, due datetime)');
            $stmt = self::$connection->prepare('insert into test_data (name, description, due) values (?,?,?)');
            
            $due = new \DateTime('20291002');
            $stmt->bindValue(1, 'first', \PDO::PARAM_STR);
            $stmt->bindValue(2, 'this is the first object', \PDO::PARAM_STR);
            $stmt->bindValue(3, $due->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->execute();
            
            $due = new \DateTime('20291005');
            $stmt->bindValue(1, 'second', \PDO::PARAM_STR);
            $stmt->bindValue(2, 'this is the second object', \PDO::PARAM_STR);
            $stmt->bindValue(3, $due->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), \PDO::PARAM_STR);
            $stmt->execute();
            
            self::$connection->commit();
        } catch (\Exception $e) {
            self::$connection->rollBack();
            throw $e;
        }
        self::$driver = new PDODriver(self::$connection);
        self::$driver->setDateTimeZone('UTC');
        self::$driver->setDateTimeFormat('Y-m-d H:i:s');
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table test_data');
    }
    
    public function testInterface(): void
    {
        $this->assertInstanceOf(DbDriver::class, self::$driver, 'interface implementation test failed');
    }

    public function testFetch(): void
    {
        $d = new \DateTime('20291005');
        $result = self::$driver->query('select * from test_data where due = :due', ['due' => $d], ['due' => 'date']);
        $this->assertNotEmpty($result, 'fetch by date attr test failed');
        $this->assertEquals($d->getTimestamp(), $result[0]->due->getTimestamp(), 'fetch result test failed');
    }
   
    public function testIteration(): void
    {
        $result = self::$driver->iterate('select * from test_data where id > 0');
        $arr = [];
        while ($result->next()) {
           $arr[] = $result->current();
        }
        $this->assertNotEmpty($arr, 'iterate test failed');
        $this->assertEquals(2, count($arr), 'iterations count test failed');
        $this->assertEquals(1, $arr[0]->id, 'interate result test failed');
    }

    public function testGet(): void
    {
        $result = self::$driver->get('select * from test_data where id = ?', [1 => 1]);
        $this->assertNotNull($result, 'get test failed');
        $this->assertEquals(1, $result->id, 'get result test failed');
    }
    
    public function testScalar(): void
    {
        $result = self::$driver->scalar('select name from test_data where id = ?', [1 => 1]);
        $this->assertEquals('first', $result, 'scalar test failed');
    }

    public function testExecute(): void
    {
        $out = [];
        self::$driver->execute('insert into test_data (name, description, due) values (?, ?, ?)', [1 => 'third', 2 => 'the third object', 3 => new \DateTime()], $out);
        $id = $out['rowid'];
        $this->assertNotEmpty($id, 'insertion test failed');
        $out = [];
        self::$driver->execute('update test_data set name = ? where id = ?', [1 => 'its third', 2 => $id], $out);
        $this->assertEquals(1, $out['affected'], 'update test failed');
        $out = [];
        self::$driver->execute('delete from test_data where id = ?', [1 => $id], $out);
        $this->assertEquals(1, $out['affected'], 'update test failed');
    }
    
    public function testTransaction(): void
    {
        $id = null;
        self::$driver->begin();
        try {
            $out = [];
            self::$driver->execute('insert into test_data (name, description, due) values (?, ?, ?)', [1 => 'fourth', 2 => 'the fourth object', 3 => new \DateTime()], $out);
            $id = $out['rowid'];
            self::$driver->commit();
        } catch (\Exception $e) {
            self::$driver->rollback();
        }
        
        $check = self::$driver->scalar('select name from test_data where id = ?', [1 => $id]);
        $this->assertEquals('fourth', $check, 'successfull transaction test failed');
        
        self::$driver->begin();
        try {
            self::$driver->begin();
            try {
                self::$driver->execute('update test_data set = ? where id = ?', [1 => 'new name', 2 => $id], $out);
                self::$driver->commit();
            } catch (\Exception $e1) {
                self::$driver->rollback();
            }
            throw new \Exception('Special');
            self::$driver->commit();
        } catch (\Exception $e) {
            self::$driver->rollback();
        }
        
        $check = self::$driver->scalar('select name from test_data where id = ?', [1 => $id]);
        $this->assertEquals('fourth', $check, 'transaction rollback test failed');
    }
}