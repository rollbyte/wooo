<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Config;

class ConfigTest extends TestCase
{
    public function testSet(): Config
    {
        $config = new Config([]);
        $config->set('exact', 'theVal');
        $config->set('nullVal', null);
        $this->assertTrue(true, 'set config value test failed');
        return $config;
    }
    
    /**
     * @depends testSet
     */
    public function testGet(Config $config): void
    {
        $this->assertNull($config->get('some'), 'get nonexistent config value test failed');
        $this->assertEquals('defVal', $config->get('some', 'defVal'), 'get default on nonexistent config value test failed');
        $this->assertNull($config->get('nullVal'), 'get null config value test failed');
        $this->assertEquals('defVal', $config->get('nullVal', 'defVal'), 'get default on null config value test failed');
        $this->assertEquals('theVal', $config->get('exact', 'defVal'), 'get existing config value test failed');
    }
}