<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\lib\dbal\PDODriver;
use wooo\lib\auth\User;
use wooo\core\Session;
use wooo\core\Request;
use wooo\lib\auth\OAuth2Passport;

class OAuthPassportTest extends TestCase
{
    private static $connection;
    private static $driver;
    
    private static $__SESSION = [];
    
    public static function setUpBeforeClass(): void
    {
        self::$connection = new \PDO('sqlite::memory:', null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        self::$connection->beginTransaction();
        self::$connection->exec('create table users (uid integer primary key autoincrement, login text unique, email text unique, active boolean)');
        self::$driver = new PDODriver(self::$connection);
    }
    
    public static function tearDownAfterClass(): void
    {
        self::$connection->exec('drop table users');
    }
    
    public function testConstructor(): OAuth2Passport
    {
        $session = $this->getMockBuilder(Session::class)
        ->disableOriginalConstructor()
        ->setMethods(['get', 'set', 'reset', 'id'])
        ->getMock();
        
        $session->method('get')->will($this->returnCallback(function ($nm) {return self::$__SESSION[$nm];}));
        $session->method('set')->will($this->returnCallback(function ($nm, $v) use ($session) {self::$__SESSION[$nm] = $v;return $session;}));
        
        $session->set('oauth2state_1', 'valid');
        
        $req = $this->getMockBuilder(Request::class)
        ->disableOriginalConstructor()
        ->setMethods(['session', 'checkPath', 'parsePath', 'getMethod'])
        ->getMock();
        
        $req->method('session')->will($this->returnValue($session));
        
        $passport = $this->getMockBuilder(OAuth2Passport::class)
            ->setConstructorArgs([$req, self::$driver, '1', 'secret', 'http://www.callback.oauth.org'])
            ->setMethods(['getAccess'])
            ->getMockForAbstractClass();
        
        $passport->method('getAccess')->will($this->returnValue(['email' => 'some@mail.org']));
        $passport->setTableName('users');
        $this->assertInstanceOf(OAuth2Passport::class, $passport, 'constructor test failed');
        return $passport;
    }
    
    /**
     * @depends testConstructor
     */
    public function testAuthError(OAuth2Passport $passport): void
    {
        $this->expectExceptionMessage('OAuth2: invalid requester');
        $passport->authorise(['error' => '401', 'error_description' => 'invalid requester']);
    }
    
    /**
     * @depends testConstructor
     */
    public function testLogin(OAuth2Passport $passport): void
    {   
        $u = $passport->authorise(['state' => 'valid', 'code' => 'auth_code']);
        $this->assertInstanceOf(User::class, $u, 'authentication test failed');
        
        $url = $passport->authURL();
        
        $state = self::$__SESSION['oauth2state_1'];
        
        $this->assertEquals(
            '?client_id=1&state=' . $state . '&redirect_uri=http%3A%2F%2Fwww.callback.oauth.org',
            $url,
            'auth url generation test failed'
        );
        $this->expectExceptionMessage('OAuth state is invalid.');
        $u = $passport->authorise(['state' => 'valid', 'code' => 'auth_code']);
    }
}