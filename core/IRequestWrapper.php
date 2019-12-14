<?php
namespace wooo\core;

interface IRequestWrapper
{
    function __construct(Request $req);
}