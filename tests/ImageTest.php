<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\FileSystem;
use wooo\core\LocalFile;
use wooo\core\Image;

class ImageTest extends TestCase
{
    const CONTENTS = 'Mary had a little lamb.';
    
    public static function setUpBeforeClass(): void
    {
        FileSystem::forceDir(FileSystem::path([__DIR__, 'tmp2']));
        
        $image = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($image , 255, 127, 127);
        imagefilledrectangle($image, 10, 10, 90, 90, $color);
        imagejpeg($image, FileSystem::path([__DIR__, 'tmp2', 'Mary.jpeg']));
    }
    
    public static function tearDownAfterClass(): void
    {
        FileSystem::deleteDir(FileSystem::path([__DIR__, 'tmp2']));
    }
    
    public function testConvert(): void
    {
        $img = new Image(new LocalFile(FileSystem::path([__DIR__, 'tmp2', 'Mary.jpeg'])));
        $img->convert(IMAGETYPE_PNG, 50, 50, false, FileSystem::path([__DIR__, 'tmp2', 'Mary.png']));
        $this->assertFileExists(FileSystem::path([__DIR__, 'tmp2', 'Mary.jpeg']));
    }
}
