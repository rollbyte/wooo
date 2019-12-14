<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class Token
{
    private $value;
    
    public function __construct($param)
    {
        if (!$param) {
            $param = 32;
        }
        if (is_int($param)) {
            $this->value = random_bytes($param);
        } elseif (is_string($param)) {
            $len = mb_strlen($param, '8bit');
            if ($len % 2 == 1) {
                throw new CoreException(CoreException::INVALID_MASKED_TOKEN_VALUE);
            }
            $len  = $len / 2;
            $this->value = mb_substr($param, $len, $len, '8bit') ^ mb_substr($param, 0, $len, '8bit');
        } else {
            throw new CoreException(CoreException::INVALID_TOKEN_LENGTH);
        }
    }
    
    public function value(): string
    {
        return $this->value;
    }
    
    public function masked(): string
    {
        $mask = new Token(mb_strlen($this->value, '8bit'));
        $mask = $mask->value();
        return $mask . ($mask ^ $this->value);
    }
    
    public function __toString()
    {
        return $this->value();
    }
}
