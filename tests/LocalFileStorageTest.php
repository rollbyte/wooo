<?php
namespace wooo\tests;

use wooo\core\LocalFile;
use wooo\stdlib\fs\LocalFileStorage;
use PHPUnit\Framework\TestCase;
use wooo\core\IFile;
use wooo\core\FileSystem;
use wooo\core\App;

class LocalFileStorageTest extends TestCase
{   
    private static function tmpDir() {
        return dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp3';
    }
    
    private static function storagePath()
    {
        return self::tmpDir() . DIRECTORY_SEPARATOR . 'wooo_lfs_test_files';
    }

    private static function tempFile()
    {
        return @tempnam(self::tmpDir(), 'wooo');
    }
    
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(self::tmpDir());
        
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(self::tmpDir());
    }
    
    private function storage(): LocalFileStorage
    {
        $app = $this->getMockBuilder(App::class)
        ->disableOriginalConstructor()
        ->setMethods(['appPath', 'appBase', 'appRoot', 'config', 'scope', 'request', 'response'])
        ->getMock();
        return new LocalFileStorage($app, self::storagePath());
    }

    public function testAccept(): string
    {
        $fn = self::tempFile();
        $id = $this->storage()->accept(new LocalFile($fn));
        $this->assertNotNull($id, 'Empty file id obtained!');
        unlink($fn);
        return $id;
    }
    
    /**
     * @depends testAccept
     */
    public function testGet(string $id): string
    {
        $f = $this->storage()->get($id);
        $this->assertInstanceOf(IFile::class, $f, 'Get file test failed');
        $this->assertFileExists($f->path(), 'Accepted file does not exist');
        return $id;
    }

    /**
     * @depends testGet
     */
    public function testDelete(string $id)
    {
        $fs = $this->storage();
        $fs->delete($id);
        $f = $fs->get($id);
        $this->assertEmpty($f, 'Delete file test failed');
    }
}