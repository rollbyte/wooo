<?php

namespace wooo\core;

interface IImage extends IFile
{
  
    public function convert(int $type, $width = null, $height = null, $crop = false, $filename = null): IFile;
}
