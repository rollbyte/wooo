<?php

namespace wooo\core;

interface IRequestWrapper
{
    public function __construct(Request $req);
}
