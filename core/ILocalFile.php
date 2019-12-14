<?php

namespace wooo\core;

interface ILocalFile extends IFile
{
    public function delete(): bool;
}
