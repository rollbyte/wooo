<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;
use wooo\core\Stream;
use wooo\core\IStream;

class StreamTest extends TestCase
{   
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(FileSystem::path([__DIR__, 'tmp6']));
        file_put_contents(FileSystem::path([__DIR__, 'tmp6', 'Mary.info']), 'Mary had a little lamb.');
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(FileSystem::path([__DIR__, 'tmp6']));
    }
    
    public function testSize(): void
    {
        $fd = fopen(FileSystem::path([__DIR__, 'tmp6', 'Mary.info']), 'r');
        $s = new Stream($fd);
        $this->assertEquals(23, $s->size(), 'stream size test failed');
        $s->close();
    }
    
    public function testRead(): void
    {
        $fd = fopen(FileSystem::path([__DIR__, 'tmp6', 'Mary.info']), 'r');
        $s = new Stream($fd);

        $result = '';
        while (!$s->eof()) {
            $chunk = $s->read(2);
            $result .= $chunk;
        }
        $this->assertEquals('Mary had a little lamb.', $result, 'stream reading test failed');
        $s->close();
    }
    
    public function testSeek(): void
    {
        $fd = fopen(FileSystem::path([__DIR__, 'tmp6', 'Mary.info']), 'r');
        $s = new Stream($fd);
        $s->seek(5);
        $result = $s->read(3);
        $this->assertEquals('had', $result, 'stream reading test failed');
        $result = '';
        $s->seek(3, IStream::SEEK_REL);
        while (!$s->eof()) {
            $chunk = $s->read(6);
            $result .= $chunk;
        }
        $this->assertEquals('little lamb.', $result, 'stream reading test failed');
        $s->close();
    }
    
    public function testRewind(): void
    {
        $fd = fopen(FileSystem::path([__DIR__, 'tmp6', 'Mary.info']), 'r');
        $s = new Stream($fd);
        $s->seek(22);
        $s->read(5);
        $this->assertTrue($s->eof(), 'stream eof test failed');
        $s->rewind();
        $chunk = $s->read(4);
        $this->assertEquals('Mary', $chunk, 'stream rewind test failed');
        $s->close();
    }
}
