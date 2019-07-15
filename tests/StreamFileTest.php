<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;
use wooo\core\StreamFile;

class StreamFileTest extends TestCase
{
    const CONTENTS = 'Mary had a little lamb.';
    
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(FileSystem::path([__DIR__, 'tmp5']));
        file_put_contents(FileSystem::path([__DIR__, 'tmp5', 'Mary.info']), self::CONTENTS);
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(FileSystem::path([__DIR__, 'tmp5']));
    }
    
    public function testAttrs(): void
    {
        $f = new StreamFile('Mary.info', 'file://' . FileSystem::path([__DIR__, 'tmp5', 'Mary.info']));
        $this->assertEquals('file://' . FileSystem::path([__DIR__, 'tmp5', 'Mary.info']), (string)$f, 'file uri test failed');
        $this->assertEquals('Mary.info', $f->getName(), 'file name test failed');
        $this->assertNull($f->getMimeType(), 'file implicit mime type test failed');
        $this->assertNull($f->getSize(), 'file implicit size test failed');
    }
    
    public function testContents(): void
    {
        $f = new StreamFile('Mary.info', 'file://' . FileSystem::path([__DIR__, 'tmp5', 'Mary.info']));
        $c = $f->getContents();
        $this->assertEquals(23, $f->getSize(), 'file size by contents test failed');
        $this->assertEquals(self::CONTENTS, $c, 'file contents test failed');
        $this->assertEquals('text/plain', $f->getMimeType(), 'file mime type by contents test failed');
    }
    
    public function testStream(): void
    {
        $f = new StreamFile('Mary.info', 'file://' . FileSystem::path([__DIR__, 'tmp5', 'Mary.info']));
        $s = $f->getStream();
        $this->assertEquals(23, $f->getSize(), 'file size by stream test failed');
        $c = $s->read(30);
        $this->assertEquals(self::CONTENTS, $c, 'file stream contents test failed');
    }
    
    public function testSave(): void
    {
        $f = new StreamFile('Mary.info', 'file://' . FileSystem::path([__DIR__, 'tmp5', 'Mary.info']));
        $f->saveAs(FileSystem::path([__DIR__, 'tmp5', 'Mary2.info']));
        $this->assertFileExists(FileSystem::path([__DIR__, 'tmp5', 'Mary2.info']), 'file saving test failed.');
    }
}
