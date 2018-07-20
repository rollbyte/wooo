<?php
namespace wooo\core;

final class Hash {
	const MD5 = "md5";
	const SHA1 = "sha1";
	const CRC32 = "crc32";
	const WHIRLPOOL = "whirlpool";
	const STD_DES = "des_std";
	const EXT_DES = "des_ext";
	const BLOWFISH = "blowfish";
	const SHA256 = "sha256";
	const SHA512 = "sha512";

	public static $DefaultAlgorythm = self::MD5;

	/**
	 * @param string $data
	 * @param string $algo
	 * @return string
	 */
	public static function apply($data, $algo = null){
		if (is_null($algo)) $algo = self::$DefaultAlgorythm;
		if (!self::checkAlgorythm($algo))
			throw new \Exception('Invalid hashing algorythm!');
		return hash($algo, $data);
	}

	private static function salt($n){
		$r = '';
		for ($i = 0; $i < $n; $i++)
			switch (mt_rand(0,5) % 3){
				case 0:$r .= chr(mt_rand(0x2E,0x39));break;
				case 1:$r .= chr(mt_rand(0x41,0x5A));break;
				case 2:$r .= chr(mt_rand(0x61,0x7A));break;
		}
		return $r;
	}

	public static function crypt($data, $algo = null){
		$salt = null;
		if ($algo){
			switch ($algo){
				case self::MD5:$salt = '$1$'.self::salt(12).'$';break;
				case self::STD_DES:self::salt(2);break;
				case self::EXT_DES:$salt = '_'.self::salt(8);break;
				case self::BLOWFISH:$salt = '$2a$05$'.self::salt(22).'$';break;
				case self::SHA256:$salt = '$5$rounds=5000$'.self::salt(16).'$';break;
				case self::SHA512:$salt = '$6$rounds=5000$'.self::salt(16).'$';break;
			}
		}

		if ($salt)
			return crypt($data, $salt);
		return crypt($data);
	}

	public static function checkHash($data, $hash){
		return crypt($data, $hash) == $hash;
	}

	/**
	 * @param string $algo
	 * @return boolean
	 */
	public static function checkAlgorythm($algo){
		return in_array($algo, hash_algos());
	}
}