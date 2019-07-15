<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;
use wooo\core\UploadedFile;

class UploadedFileTest extends TestCase
{
    const CONTENTS = 'Mary had a little lamb.';
    
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(FileSystem::path([__DIR__, 'tmp7']));
        file_put_contents(FileSystem::path([__DIR__, 'tmp7', 'Mary.info']), self::CONTENTS);
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(FileSystem::path([__DIR__, 'tmp7']));
    }
    
    public function testSave(): void
    {
        $f = $this->getMockBuilder(UploadedFile::class)
        ->setConstructorArgs([[
            'name' => 'Mary2.info',
            'tmp_name' => FileSystem::path([__DIR__, 'tmp7', 'Mary.info'])
        ]])
        ->setMethods(['moveUploaded'])
        ->getMock();
        
        $f->method('moveUploaded')->will($this->returnCallback(function ($dest) use ($f) {
            rename($f->path(), $dest);
            return true;
        }));
        
        $f->saveAs(FileSystem::path([__DIR__, 'tmp7', 'Mary2.info']));
        $this->assertFileExists(FileSystem::path([__DIR__, 'tmp7', 'Mary2.info']), 'file saving test failed.');
    }
}
