<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

final class Hash
{
    
    const MD5 = "md5";
    
    const SHA1 = "sha1";
    
    const CRC32 = "crc32";
    
    const WHIRLPOOL = "whirlpool";
    
    const STD_DES = "des_std";
    
    const EXT_DES = "des_ext";
    
    const BLOWFISH = "blowfish";
    
    const SHA256 = "sha256";
    
    const SHA512 = "sha512";
    
    private $algo;
    
    public function __construct(string $algo = self::MD5)
    {
        $this->algo = $algo;
    }

    public function apply(string $data, ?string $key = null): string
    {
        if ($key) {
            if (!in_array($this->algo, hash_hmac_algos())) {
                throw new CoreException(CoreException::INVALID_HASH_ALGO);
            }
            return hash_hmac($this->algo, $data, $key);
        }
        if (!in_array($this->algo, hash_algos())) {
            throw new CoreException(CoreException::INVALID_HASH_ALGO);
        }
        return hash($this->algo, $data);
    }
}
