<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\lib\dbal\PDODriver;
use wooo\lib\auth\LocalPassport;
use wooo\core\PasswordHash;
use wooo\lib\auth\User;

class LocalPassportTest extends TestCase
{
    private static $connection;
    private static $driver;
    private static $passport;
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        self::$connection->exec('create table users (uid integer primary key autoincrement, login text unique, pwd text, active boolean)');
        self::$driver = new PDODriver(self::$connection);
        self::$passport = new LocalPassport(self::$driver);
        self::$passport->setTableName('users');
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table users');
    }
    
    public function testLogin(): void
    {
        $ph = new PasswordHash();
        $pwd = $ph->apply('12345');
        self::$connection->exec('insert into users (login, pwd, active) values (\'user1\', \'' . $pwd . '\', 1)');
        $u = self::$passport->authenticate(['login' => 'user1', 'pwd' => '12345']);
        $this->assertInstanceOf(User::class, $u, 'authentication test failed');
    }
}