<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;
use wooo\core\Scope;
use wooo\core\App;
use wooo\tests\util\ComponentMock;
use wooo\tests\util\ComponentMock3;
use wooo\tests\util\ComponentMock2;

class ScopeTest extends TestCase
{
    public function testConstructor(): Scope
    {   
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->setMethods(['set', 'get'])
            ->getMock();
        
        $config->method('get')
            ->will($this->returnCallback(function ($nm) {return $nm;}));
          
        $app = $this->getMockBuilder(App::class)
            ->disableOriginalConstructor()
            ->setMethods(['appPath', 'appBase', 'appRoot', 'config', 'scope'])
            ->getMock();
        
        $app->method('appPath')->will($this->returnValue('/home'));
        $app->method('appBase')->will($this->returnValue('http://localhost/'));
        $app->method('appRoot')->will($this->returnValue('/'));
        $app->method('config')->will($this->returnValue($config));
            
        $scope = new Scope([
            'com1' => [
                'class' => 'wooo\tests\util\${ComponentMock}',
                'args' => ['${value1}'],
            ],
            'com2' => [
                'class' => 'wooo\tests\util\ComponentMock',
                'args' => ['value2', '${com1}'],
                'options' => [
                    'prefix' => 'bar'
                ]
            ],
            'com3' => [
                'class' => 'wooo\tests\util\ComponentMock',
                'args' => ['value3', 'com1'],
                'options' => [
                    'dependency2' => '${com2}'
                ]
            ],
            'com4' => [
                'class' => 'wooo\tests\util\ComponentMock2'
            ]
        ], $app);
        
        $this->assertInstanceOf(Scope::class, $scope, 'scope initialization test failed');
        $this->assertInstanceOf(ComponentMock::class, $scope->com1, 'scope component initialization test failed');
        $this->assertInstanceOf(ComponentMock::class, $scope->com2, 'scope component initialization test failed');
        $this->assertInstanceOf(ComponentMock::class, $scope->com3, 'scope component initialization test failed');
        
        $app->method('scope')->will($this->returnValue($scope));
        return $scope;
    }
    
    /**
     * @depends testConstructor
     */
    public function testComponents(Scope $scope): Scope
    {
        $this->assertEquals('/home', $scope->com1->getContext(), 'component application object injection text failed');
        $this->assertEquals('value1', $scope->com1->getValue(), 'component simple constructor arg test failed');
        $c2v = $scope->com2->getValue();
        $this->assertStringStartsWith('bar.', $c2v, 'component simple option setting test failed');
        $this->assertStringEndsWith(':value1', $c2v, 'component constructor arg injection test failed');
        $c3v = $scope->com3->getValue();
        $this->assertStringEndsWith(':bar.value2:value1', $c3v, 'component constructor options injection test failed');
        return $scope;
    }
    
    /**
     * @depends testComponents
     */
    public function testInject(Scope $scope): void
    {
        $scope->dynamic = function (App $app) {
            return new ComponentMock3($app->com4);  
        };
        
        $this->assertInstanceOf(ComponentMock3::class, $scope->dynamic, 'dynamic component loading test failed');
        $this->assertNotSame($scope->dynamic, $scope->dynamic, 'dynamic non equality test failed'); 
        $scope->inject([
            'wooo\tests\util\ComponentMock2' => [],
            'byClassInjection' => [
                'class' => 'wooo\tests\util\ComponentMock3'
            ],
            'nonSingle' => [
                'class' => 'wooo\tests\util\ComponentMock3',
                'produce' => true
            ]
        ]);
        $this->assertInstanceOf(ComponentMock2::class, $scope->get(ComponentMock2::class), 'injection by class test failed');
        $this->assertInstanceOf(ComponentMock3::class, $scope->byClassInjection, 'injection by class test failed');
        $this->assertNotSame($scope->nonSingle, $scope->nonSingle, 'non singleton injection test failed');
    }
}
