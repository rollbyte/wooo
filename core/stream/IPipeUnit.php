<?php
namespace wooo\core\stream;

interface IPipeUnit
{
    public function pipe(IWritableStream $destination, bool $autoClose = true): IPipeStarter;
}