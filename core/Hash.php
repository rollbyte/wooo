<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

final class Hash
{
    
    public const MD5 = "md5";
    
    public const SHA1 = "sha1";
    
    public const CRC32 = "crc32";
    
    public const WHIRLPOOL = "whirlpool";
    
    public const STD_DES = "des_std";
    
    public const EXT_DES = "des_ext";
    
    public const BLOWFISH = "blowfish";
    
    public const SHA256 = "sha256";
    
    public const SHA512 = "sha512";
    
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
