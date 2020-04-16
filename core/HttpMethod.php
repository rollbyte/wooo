<?php

namespace wooo\core;

abstract class HttpMethod {
    public const GET = 'GET';
    public const POST = 'POST';
    public const DELETE = 'DELETE';
    public const PUT = 'PUT';
    public const PATCH = 'PATCH';
    public const HEAD = 'HEAD';
    public const SEARCH = 'SEARCH';
}