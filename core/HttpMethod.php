<?php

namespace wooo\core;

abstract class HttpMethod
{
    public const GET = 'GET';
    public const POST = 'POST';
    public const DELETE = 'DELETE';
    public const PUT = 'PUT';
    public const PATCH = 'PATCH';
    public const HEAD = 'HEAD';
    public const SEARCH = 'SEARCH';
    public const OPTIONS = 'OPTIONS';

    public static function isReading($method): bool
    {
        return
            $method === self::GET ||
            $method === self::SEARCH ||
            $method === self::HEAD ||
            $method === self::OPTIONS;
    }

    public static function isWriting($method): bool
    {
        return
            $method === self::POST ||
            $method === self::PUT ||
            $method === self::PATCH ||
            $method === self::DELETE;
    }
}
