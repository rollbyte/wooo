<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Request;
use wooo\core\Session;
use wooo\lib\auth\SessionAuthenticator;
use wooo\lib\auth\interfaces\IPassport;
use wooo\lib\auth\interfaces\IUser;
use wooo\tests\util\VirtualPassport;

class SessionAuthenticatorTest extends TestCase
{
    private static $__SESSION = [];
    
    public function testConstructor(): SessionAuthenticator
    {            
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->setMethods(['get', 'set', 'reset', 'id'])
            ->getMock();
            
        $session->method('get')->will($this->returnCallback(function ($nm) {return self::$__SESSION[$nm];}));
        $session->method('set')->will($this->returnCallback(function ($nm, $v) use ($session) {self::$__SESSION[$nm] = $v; return $session;}));
            
        $req = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->setMethods(['session', 'checkPath', 'parsePath', 'getMethod'])
            ->getMock();
            
        $req->method('session')->will($this->returnValue($session));
        
        $auth = new SessionAuthenticator($req);
        
        $auth->setPassportType('virtual');
        $auth->setPassport(new VirtualPassport());
        
        $map = $auth->passports();
        
        $this->assertInstanceOf(IPassport::class, $map['virtual'], 'passport adding test failed');
            
        return $auth;
    }
    
    /**
     * @depends testConstructor
     */
    public function testLogin(SessionAuthenticator $auth): void
    {   
        $auth->login(['login' => 'user1'], 'virtual');
        
        $u1 = self::$__SESSION['curr_user'];
        $this->assertInstanceOf(IUser::class, $u1, 'login test failed');
        
        $u = $auth->user();
        
        $this->assertEquals($u1, $u, 'get current user test failed');
        
        $auth->logout();
        
        $this->assertNull(self::$__SESSION['curr_user'], 'logout test failed');
        $this->assertNull($auth->user(), 'get user after logout test failed');
        
        $auth->force($u);
        
        $this->assertInstanceOf(IUser::class, $auth->user(), 'force user test failed');
    }
}
