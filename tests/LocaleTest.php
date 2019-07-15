<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Locale;

class LocaleTest extends TestCase
{
    public function testConstructor(): Locale
    {
        $locale = new Locale('en', 'UTC');
        $this->assertInstanceOf(Locale::class, $locale);
        return $locale;
    }
    
    /**
     * @depends testConstructor
     */
    public function testDateFormat(Locale $locale): void
    {
        $d = new \DateTime('now', new \DateTimeZone('UTC'));
        $d->setDate(1917, 10, 25);
        $d->setTime(0, 0, 0, 0);
        $this->assertEquals('10/25/17, 12:00:00 AM', $locale->formatDate($d), 'datetime formatting test failed');
        $this->assertEquals('10/25/17', $locale->formatDate($d, true), 'date formatting test failed');
        
        $pd = $locale->parseDate('10/25/1917, 12:00:00 AM');
        $this->assertInstanceOf(\DateTime::class, $pd, 'datetime parsing test failed');
        $this->assertEquals($d->getTimestamp(), $pd->getTimestamp(), 'datetime parsing test failed');
        
        $pd = $locale->parseDate('10/25/1917', false);
        $this->assertInstanceOf(\DateTime::class, $pd, 'date parsing test failed');
        $this->assertEquals($d->getTimestamp(), $pd->getTimestamp(), 'date parsing test failed');
    }
    
    /**
     * @depends testConstructor
     */
    public function testLanguage(Locale $locale): void
    {
        $this->assertEquals('en', $locale->language());
    }
    
    /**
     * @depends testConstructor
     */
    public function testFormats(Locale $locale): void
    {
        $this->assertEquals('M/d/yy', $locale->dateFormat(), 'date format check failed');
        $this->assertEquals('M/d/yy, h:mm:ss a', $locale->dateTimeFormat(), 'datetime format check failed');
    }
}
