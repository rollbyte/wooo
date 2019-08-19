<?php

namespace wooo\core;

use wooo\core\stream\IReadableStream;

interface IFile
{
  
    /**
     *
     * @return string
     */
    public function getName(): string;
  
    /**
     *
     * @return string
     */
    public function getMimeType(): ?string;
  
    /**
     *
     * @return integer
     */
    public function getSize(): ?int;
  
    /**
     *
     * @return string
     */
    public function getContents(): string;
  
    public function getStream(): IReadableStream;
  
    /**
     *
     * @param  string $filename
     * @return boolean
     */
    public function saveAs($filename): bool;
}
