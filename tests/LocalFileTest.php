<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;
use wooo\core\LocalFile;

class LocalFileTest extends TestCase
{
    const CONTENTS = 'Mary had a little lamb.';
    
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(FileSystem::path([__DIR__, 'tmp4']));
        file_put_contents(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']), self::CONTENTS);
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(FileSystem::path([__DIR__, 'tmp4']));
    }
    
    public function testAttrs(): void
    {
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']));
        $this->assertEquals('Mary.info', $f->getName(), 'file name test failed');
        $this->assertEquals(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']), $f->path(), 'file path test failed');
        $this->assertEquals('text/plain', $f->getMimeType(), 'file implicit mime type test failed');
        $this->assertNull($f->getSize(), 'file unknown size test failed');
        
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']), null, 'text/javascript', 23);
        $this->assertEquals('text/javascript', $f->getMimeType(), 'file explicit mime type test failed');
        $this->assertEquals(23, $f->getSize(), 'file explicit size test failed');
    }
    
    public function testContents(): void
    {
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']));
        $this->assertNull($f->getSize(), 'file unknown size test failed');
        $s = $f->getContents();
        $this->assertEquals(23, $f->getSize(), 'file size by contents test failed');
        $this->assertEquals(self::CONTENTS, $s, 'file get contents test failed');
    }
    
    public function testStream(): void
    {
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']));
        $this->assertNull($f->getSize(), 'file unknown size test failed');
        $s = $f->getStream();
        $this->assertEquals(23, $f->getSize(), 'file size by stream test failed');
        $this->assertEquals(self::CONTENTS, $s->read(30), 'file read stream test failed');
        $s->close();
    }
    
    public function testSaveNDel(): void
    {
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary.info']));
        $f->saveAs(FileSystem::path([__DIR__, 'tmp4', 'Mary2.info']));
        $this->assertFileExists(FileSystem::path([__DIR__, 'tmp4', 'Mary2.info']), 'file saving test failed.');
        $f = new LocalFile(FileSystem::path([__DIR__, 'tmp4', 'Mary2.info']));
        $f->delete();
        $this->assertFileNotExists(FileSystem::path([__DIR__, 'tmp4', 'Mary2.info']), 'file saving test failed.');
    }
}
