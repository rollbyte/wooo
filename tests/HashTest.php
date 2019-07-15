<?php
namespace wooo\tests;

use PHPUnit\Framework\TestCase;
use wooo\core\Hash;
use wooo\core\exceptions\CoreException;

class HashTest extends TestCase
{
    public function testHashValidAlgo(): void
    {
        $h = new Hash();
        $hash = $h->apply('12345');
        
        $this->assertEquals('827ccb0eea8a706c4c34a16891f84e7b', $hash, 'md5 hashing test failed');
        
        $h = new Hash(Hash::CRC32);
        $hash = $h->apply('12345');
        
        $this->assertEquals('b8486542', $hash, 'crc32 hashing test failed');
        
        // TODO test other hash types
    }
    
    public function testHashInvalidAlgo(): void
    {
        $this->expectExceptionCode(CoreException::INVALID_HASH_ALGO);
        $h = new Hash('foo');
    }
}