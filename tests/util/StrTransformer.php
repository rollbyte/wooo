<?php
namespace wooo\tests\util;

use wooo\core\stream\TransformStream;

class StrTransformer extends TransformStream
{
    protected function transform($data)
    {
        return strrev($data);
    }
}