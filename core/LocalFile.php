<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class LocalFile implements ILocalFile
{
  
    /**
     *
     * @var string file name
     */
    protected $name;
  
    /**
     *
     * @var string file mime type
     */
    protected $mimeType;
  
    /**
     *
     * @var string local path of file
     */
    protected $path;
  
    /**
     *
     * @var int file size
     */
    protected $size;
  
    /**
     * constructor.
     * fills file object attributes.
     *
     * @param string $name
     *          file name
     * @param string $path
     *          file path
     * @param string $type
     *          optional mime type
     * @param int    $size
     *          optional size
     */
    public function __construct($name, $path = null, $type = null, $size = null)
    {
        $this->name = $name;
    
        if (!$path) {
            $path = $name;
            $this->name = basename($path);
        }
    
        if ($path == null || !FileSystem::isAbsolute($path)) {
            throw new CoreException(CoreException::PATH_NOT_ABSOLUTE);
        }
        
        $this->path = $path;
        $this->size = $size;
        $this->mimeType = $type;
        if (!$this->mimeType) {
            if (function_exists("finfo_file") && !parse_url($path, PHP_URL_SCHEME) && file_exists($path)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE | FILEINFO_PRESERVE_ATIME);
                $this->mimeType = finfo_file($finfo, $path);
                finfo_close($finfo);
            }
        }
    }
  
    /**
     * gets file contents
     *
     * @return string
     */
    public function getContents(): string
    {
        $r = file_get_contents($this->path);
        $this->size = strlen($r);
        return $r;
    }
  
    public function getStream(): IStream
    {
        $s = new Stream(fopen($this->path, 'r'));
        $this->size = $s->size();
        return $s;
    }
  
    public function getSize(): ?int
    {
        return $this->size;
    }
  
    /**
     * file string representation.
     * by default evaluates to storage path.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->getPath();
    }
  
    public function getName(): string
    {
        return $this->name;
    }
  
    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }
  
    public function saveAs($filename): bool
    {
        if (!FileSystem::isAbsolute($filename)) {
            throw new CoreException(CoreException::PATH_NOT_ABSOLUTE);
        }
        FileSystem::forceDir(dirname($filename));
        copy($this->path, $filename);
        return true;
    }
  
    public function delete(): bool
    {
        unlink($this->path);
        return true;
    }
    
    public function path(): string
    {
        return $this->path;
    }
}
