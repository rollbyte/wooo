<?php

namespace wooo\core;

interface IImage extends IFile
{
  
    public function convert(
        ?int $type = null,
        ?int $width = null,
        ?int $height = null,
        bool $crop = false,
        ?string $filename = null
    ): IFile;
}
