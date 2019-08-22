<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\lib\dbal\PDODriver;
use wooo\lib\orm\Mapper;
use wooo\tests\orm\ClassD;
use wooo\tests\orm\ClassC;
use wooo\tests\orm\ClassA;
use wooo\tests\orm\ClassB;
use wooo\tests\orm\ClassE;
use wooo\lib\orm\FO;
use wooo\lib\orm\AGGREG;
use wooo\tests\orm\ClassF;
use wooo\core\DateTime;

class MapperTest extends TestCase
{
    private static $connection;
    private static $driver;
    private static $mapper;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->exec('create table class_a (d text, id text, class_name text, name text, date_attr datetime default CURRENT_TIMESTAMP, a text, primary key (d, id))');
        self::$connection->exec('create table class_b (d text, id text, bool_attr bool, float_attr float, int_attr integer, primary key (d, id))');
        self::$connection->exec('create table class_c (code text primary key, name text, d text, a text)');
        self::$connection->exec('create table class_d (code text primary key, name text)');
        self::$connection->exec('create table class_e (code text, master text, detail text, primary key (code, master, detail))');
        self::$connection->exec('create table class_f (id integer primary key autoincrement, name text)');
        self::$driver = new PDODriver(self::$connection);
        self::$driver->setDateTimeZone('UTC');
        self::$driver->setDateTimeFormat('Y-m-d H:i:s');
        self::$mapper = new Mapper(self::$driver, 'wooo\tests\orm');
        self::$mapper->setDescendants([ClassA::class => ClassB::class]);
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table class_a');
        self::$connection->exec('drop table class_b');
        self::$connection->exec('drop table class_c');
        self::$connection->exec('drop table class_d');
        self::$connection->exec('drop table class_e');
        self::$connection->exec('drop table class_f');
    }
    
    public function testCreate(): void
    {
        $result = self::$mapper->create(ClassD::class, ['Code' => 'code1', 'Name' => 'name 1']);
        $this->assertInstanceOf(ClassD::class, $result, 'simple creation test failed');
        $this->assertEquals('code1', $result->Code, 'attr check test failed');
        
        $result = self::$mapper->create(ClassF::class, ['Name' => 'name 1']);
        $this->assertInstanceOf(ClassF::class, $result, 'creation with autoincrement id test failed');
        
        $dref = self::$mapper->create(ClassD::class, ['Code' => 'code2', 'Name' => 'name 2']);
        
        $d = new \DateTime('20191009');
        
        $result = self::$mapper->create(
            ClassB::class,
            [
                'Dref' => $dref,
                'Id' => '00001',
                'Name' => 'first B instance',
                'DateAttr' => $d,
                'BoolAttr' => true,
                'FloatAttr' => 25.6,
                'IntAttr' => 156
            ],
            [
                'eager' => ['Dref']
            ]
        );
        $this->assertInstanceOf(ClassB::class, $result, 'subclass creation test failed');
        $this->assertEquals(25.6, $result->FloatAttr, 'float attr assignment test failed');
        $this->assertInstanceOf(\DateTime::class, $result->DateAttr, 'date attr type test failed');
        $this->assertEquals($d->getTimestamp(), $result->DateAttr->getTimestamp(), 'date attr assignment test failed');
        $this->assertEquals(25.6, $result->FloatAttr, 'float attr assignment test failed');
        $this->assertEquals(156, $result->IntAttr, 'int attr assignment test failed');
        $this->assertInstanceOf(ClassD::class, $result->Dref, 'ref attr assignment test failed');
        
        self::$mapper->create(
            ClassB::class,
            [
                'Dref' => $dref,
                'Id' => '00002',
                'Name' => 'second B instance',
                'DateAttr' => $d,
                'BoolAttr' => false,
                'FloatAttr' => 21.6,
                'IntAttr' => 176
            ],
            [
                'eager' => ['Dref']
            ]
        );

        $d->setDate(2019, 11, 12);
        self::$mapper->create(
            ClassA::class,
            [
                'Dref' => $dref,
                'Id' => '00003',
                'Name' => 'first A instance',
                'DateAttr' => $d
            ],
            [
                'eager' => ['Dref']
            ]
        ); 
        
        self::$mapper->create(ClassC::class, ['Code' => 'code1', 'Name' => 'C # 1', 'Dref' => 'code2', 'Aref' => '00002']);
        self::$mapper->create(ClassC::class, ['Code' => 'code2', 'Name' => 'C # 2', 'Dref' => 'code2', 'Aref' => '00002']);
        self::$mapper->create(ClassE::class, ['Dref' => 'code2', 'Master' => '00002', 'Detail' => '00001']);
        self::$mapper->create(ClassE::class, ['Dref' => 'code2', 'Master' => '00002', 'Detail' => '00003']);
    }
    
    /**
     * @depends testCreate
     */
    public function testEdit(): void
    {
        $result = self::$mapper->edit(ClassA::class, 'code2|00001', ['Dref' => 'code1'], ['eager' => ['Dref']]);
        $this->assertInstanceOf(ClassB::class, $result, 'editing by string id test failed');
        $this->assertInstanceOf(ClassD::class, $result->Dref, 'eager loading on edit test failed');
        $this->assertEquals('code1', $result->Dref->Code, 'edit test failed');
        
        $result = self::$mapper->edit(ClassB::class, ['code1','00001'], ['Dref' => 'code2'], ['eager' => ['Dref']]);
        $this->assertInstanceOf(ClassB::class, $result, 'editing by array id test failed');
        $this->assertEquals('code2', $result->Dref->Code, 'edit test failed');
        
        $result = self::$mapper->edit(ClassA::class, ['code2','00002'], ['Aref' => '00003'], ['eager' => ['Aref']]);
        $this->assertInstanceOf(ClassA::class, $result->Aref, 'editing complex ref with immutable field test failed');
    }
    
    /**
     * @depends testEdit
     */
    public function testSave(): void
    {
        $obj = self::$mapper->getById(ClassA::class, ['code2','00001']);
        $this->assertInstanceOf(ClassB::class, $obj, 'get by id for save test failed');
        $obj->BoolAttr = false;
        $obj = self::$mapper->save($obj);
        $this->assertFalse($obj->BoolAttr, 'save test failed');
    }
    
    public function testKey(): void
    {
        $obj = new ClassB();
        $d = new ClassD();
        $d->Code = 'code1';
        $obj->Dref = $d;
        $obj->Id = '00001';
        $this->assertEquals('code1|00001', self::$mapper->key($obj), 'object string key test failed');
        $this->assertEquals(['code1','00001'], self::$mapper->key($obj, false), 'object array key test failed');
    }
    
    /**
     * @depends testEdit
     */
    public function testFetch(): void
    {
        $fd = new DateTime('20191101');
        $result = self::$mapper->fetch(
            ClassB::class,
            [
                'filter' => [
                    FO::AND => [
                        [FO::LT => ['DateAttr', ':fd']],
                        [FO::NE => ['DateAttr', null]],
                        [FO::LIKE => ['Name', ':q']]
                    ]
                ],
                'eager' => ['Dref', 'Aref', 'Ccol', 'Ecol.Detail']
            ],
            ['fd' => $fd, 'q' => 'first%']
        );
        $this->assertNotEmpty($result, 'simple fetch test failed');
        $result = self::$mapper->fetch(
            ClassB::class,
            [
                'filter' => [FO::LT => ['Ecol.Detail.DateAttr', $fd]],
                'eager' => ['Dref', 'Aref', 'Ccol', 'Ecol.Detail']
            ]
        );
        $this->assertNotEmpty($result, 'fetch with filter by collection item attribute test failed');
        
        $result = self::$mapper->fetch(
            ClassE::class,
            [
                'filter' => [FO::EQ => ['Master', ':m']],
            ],
            [
                'm' => $result[0]
            ]
        );
        $this->assertNotEmpty($result, 'fetch with filter by refered object test failed');
    }
    
    /**
     * @depends testFetch
     */
    public function testIterate(): void
    {
        $fd = new \DateTime('20191101');
        $result = self::$mapper->iterate(
            ClassB::class,
            [
                'filter' => [FO::LT => ['DateAttr', $fd]],
                'eager' => ['Dref', 'Aref']
            ]
        );
        
        $this->assertInstanceOf(\Traversable::class, $result, 'iterate call test failed');
        
        $iterated = false;
        
        foreach ($result as $obj) {
            if ($obj) {
                $iterated = true;
            }
        }
        
        $this->assertTrue($iterated, 'iteration test failed');
    }
    
    /**
     * @depends testIterate
     */
    public function testAggregate(): void
    {
        $fd = new \DateTime('20191101');
        $result = self::$mapper->aggregate(
                ClassB::class,
                [
                    'fetch' => [
                        'd' => 'Dref',
                        'sumfloat' => [AGGREG::SUM => 'IntAttr'],
                        'countamount' => [AGGREG::COUNT => 'Id'],
                        'maxfloat' => [AGGREG::MAX => 'IntAttr'],
                        'minfloat' => [AGGREG::MIN => 'IntAttr'],
                        'avgfloat' => [AGGREG::AVG => 'IntAttr'],
                    ],
                    'filter' => [FO::LT => ['DateAttr', $fd]]
                ]
        );
        $this->assertNotEmpty($result, 'aggregate test failed');
        foreach ($result as $stat) {
            $this->assertEquals(2, $stat->countamount, ' aggregation count func test failed');
            break;
        }
    }
    
    /**
     * @depends testAggregate
     */
    public function testCount(): void
    {
        $fd = new \DateTime('20191101');
        $result = self::$mapper->count(
                ClassB::class,
                [
                    'filter' => [FO::LT => ['DateAttr', $fd]],
                ]
                );
        $this->assertEquals(2, $result, 'count test failed');
    }
    
    /**
     * @depends testCount
     */
    public function testGet(): void
    {
        $result = self::$mapper->get(ClassB::class, [
            'filter' => [FO::EQ => ['Id', ':id']],
            'eager' => ['Dref', 'Aref', 'Ccol', 'Ecol.Detail']
        ],['id' => '00002']);
        $this->assertInstanceOf(ClassB::class, $result, 'get test failed');
    }
    
    /**
     * @depends testGet
     */
    public function testGetById(): void
    {
        $result = self::$mapper->getById(ClassB::class, ['code2', '00002'], [
            'eager' => ['Dref', 'Aref', 'Ccol', 'Ecol.Detail']
        ]);
        $this->assertEquals(176, $result->IntAttr);
        $this->assertEquals(21.6, $result->FloatAttr);
        $this->assertTrue(21.6 === $result->FloatAttr);
        $this->assertInstanceOf(ClassB::class, $result, 'get test failed');
    }
    
    /**
     * @depends testGetById
     */
    public function testAttrTypes(): void
    {
        $obj = self::$mapper->getById(ClassA::class, 'code2|00001');
        $types = self::$mapper->attrTypes($obj);
        $this->assertEquals(ClassA::class, $types->Aref->type);
        $this->assertTrue($types->Aref->ref);
        $this->assertFalse($types->Aref->collection);

        $this->assertEquals(ClassD::class, $types->Dref->type);
        $this->assertTrue($types->Dref->ref);
        $this->assertFalse($types->Dref->collection);
        
        
        $this->assertEquals(ClassC::class, $types->Ccol->type);
        $this->assertTrue($types->Ccol->ref);
        $this->assertTrue($types->Ccol->collection);

        $this->assertEquals(ClassE::class, $types->Ecol->type);
        $this->assertTrue($types->Ecol->ref);
        $this->assertTrue($types->Ecol->collection);
                
        $this->assertEquals('string', $types->ClassName->type);
        $this->assertFalse($types->ClassName->ref);
        $this->assertFalse($types->ClassName->collection);

        $this->assertEquals('string', $types->Name->type);
        $this->assertFalse($types->Name->ref);
        $this->assertFalse($types->Name->collection);
        
        $this->assertEquals('string', $types->Id->type);
        $this->assertFalse($types->Id->ref);
        $this->assertFalse($types->Id->collection);

        $this->assertEquals(\DateTime::class, $types->DateAttr->type);
        $this->assertFalse($types->DateAttr->ref);
        $this->assertFalse($types->DateAttr->collection);
        
        $this->assertEquals('bool', $types->BoolAttr->type);
        $this->assertFalse($types->BoolAttr->ref);
        $this->assertFalse($types->BoolAttr->collection);
        
        $this->assertEquals('int', $types->IntAttr->type);
        $this->assertFalse($types->IntAttr->ref);
        $this->assertFalse($types->IntAttr->collection);
        
        $this->assertEquals('float', $types->FloatAttr->type);
        $this->assertFalse($types->FloatAttr->ref);
        $this->assertFalse($types->FloatAttr->collection);
    }
    
    /**
     * @depends testAttrTypes
     */
    public function testLazyLoaders(): void
    {
        $obj = self::$mapper->getById(ClassA::class, 'code2|00001');
        $ll = self::$mapper->lazyLoaders($obj);
        $this->assertAttributeInternalType('callable', 'Aref', $ll, 'ref lazy loader get test failed');
        $this->assertAttributeInternalType('callable', 'Dref', $ll, 'ref lazy loader get test failed');
        
        $dref = ($ll->Dref)();
        $this->assertInstanceOf(ClassD::class, $dref, 'lazy loader call test failed');
        
        $this->assertAttributeInternalType('callable', 'Ccol', $ll, 'col lazy loader get test failed');
        $this->assertAttributeInternalType('callable', 'Ecol', $ll, 'col lazy loader get test failed');
    }
    
    /**
     * @depends testLazyLoaders
     */
    public function testDelete(): void
    {
        $obj = self::$mapper->getById(ClassA::class, 'code2|00001');
        $this->assertInstanceOf(ClassB::class, $obj, 'get for delete test failed');
        self::$mapper->delete(ClassA::class, 'code2|00001');
        $obj = self::$mapper->getById(ClassA::class, 'code2|00001');
        $this->assertEmpty($obj, 'delete by ancestor test failed');
    }
    
    /**
     * @depends testDelete
     */
    public function testTransaction(): void
    {
        $obj = self::$mapper->getById(ClassA::class, 'code2|00002');
        $this->assertInstanceOf(ClassB::class, $obj, 'get for delete test failed');
        self::$mapper->begin();
        try {
            self::$mapper->delete(ClassA::class, 'code2|00002');
            throw new \Exception('something went wrong');
        } catch (\Exception $e) {
            self::$mapper->rollback();
        }
        
        $obj = self::$mapper->getById(ClassA::class, 'code2|00002');
        $this->assertInstanceOf(ClassB::class, $obj, 'transaction rollback test failed');
        
        try {
            self::$mapper->delete(ClassA::class, 'code2|00002');
            self::$mapper->commit();
        } catch (\Exception $e) {
            self::$mapper->rollback();
        }
        
        $obj = self::$mapper->getById(ClassA::class, 'code2|00002');
        $this->assertEmpty($obj, 'transaction commit test failed');
    }
}
