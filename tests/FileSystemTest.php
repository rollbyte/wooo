<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;

class FileSystemTest extends TestCase
{
    public function testMakeDelDir(): void
    {
        $path = FileSystem::path([__DIR__, 'tmp1', 'fs']);
        $this->assertTrue(FileSystem::isAbsolute($path), 'absolute path check test failed');
        FileSystem::forceDir($path);
        $this->assertDirectoryExists($path, 'force directory test failed');
        
        $path = FileSystem::path([__DIR__, 'tmp1']);
        $dirs = FileSystem::listFiles($path);
        $this->assertNotEmpty($dirs, 'list directory contents test failed');
        $this->assertDirectoryExists($dirs[0]['dirname'], 'list directory is absolute test failed');
        
        FileSystem::deleteDir($path);
        $this->assertDirectoryNotExists($path, 'delete directory test failed');
    }    
}
