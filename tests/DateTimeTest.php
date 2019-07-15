<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\DateTime;

class DateTimeTest extends TestCase
{
    public function testConstructor(): DateTime
    {
        $d = new DateTime();
        $d->setTimezone(new \DateTimeZone('UTC'));
        $d->setDate(1917, 10, 25);
        $d->setTime(0, 0, 0, 0);
        $this->assertInstanceOf(\DateTime::class, $d, 'datetime constructor test failed');
        return $d;
    }
    
    /**
     * @depends testConstructor
     */
    public function testJSON(DateTime $d): void
    {
        $this->assertEquals('1917-10-25T00:00:00+0000', $d->jsonSerialize(), 'json serialisation test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testString(DateTime $d): void
    {
        $this->assertEquals('19171025000000', $d->__toString(), 'string serialisation test failed');
    }
}
