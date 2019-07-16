<?php

namespace wooo\lib\fs\interfaces;

use wooo\core\IFile;

interface IFileStorage
{
  
    public function accept(IFile $file, $path = null): string;
  
    public function get(string $id): ?IFile;
  
    public function delete(string $id): void;
}
