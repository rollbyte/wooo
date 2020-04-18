<?php

namespace wooo\core;

use ArrayAccess as ArrayAccess;
use stdClass as stdClass;

class RequestData extends stdClass implements ArrayAccess
{
    private static function acceptValue($value, $urldecode = false)
    {
        if (is_array($value)) {
            array_walk_recursive(
                $value,
                function (&$item, $key, $urldecode) {
                    $item = $urldecode ? rawurldecode($item) : $item;
                },
                $urldecode
            );
        } else {
            $value = $urldecode ? rawurldecode($value) : $value;
        }
        if (!is_null($value)) {
            return $value;
        }
        return null;
    }
    
    public function __construct($src = null, $urldecode = false)
    {
        if ($src && is_object($src)) {
            $src = (array)$src;
        }
        
        if (is_array($src)) {
            foreach ($src as $key => $value) {
                $this->$key = $this->acceptValue($value, $urldecode);
            }
        }
    }
    
    public function offsetGet($offset)
    {
        return $this->$offset;
    }

    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }
}
