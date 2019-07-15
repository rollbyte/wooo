<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class StreamFile implements IFile
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
    protected $URI;
  
    /**
     *
     * @var mixed
     */
    protected $descriptor;
  
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
     * @param string $uri
     *          stream uri
     * @param mixed  $sd
     *          stream descriptor
     * @param string $type
     *          optional mime type
     * @param int    $size
     *          optional size
     */
    public function __construct(string $name, string $uri, $sd = null, string $type = null, int $size = null)
    {
        $this->name = $name;
        $this->mimeType = $type;
        $this->URI = $uri;
        $this->size = $size ? $size : null;
        $this->descriptor = $sd ? $sd : false;
    }
    
    protected function openStream()
    {
        if (!$this->descriptor) {
            $this->descriptor = fopen($this->URI, 'rb');
            if (!is_resource($this->descriptor)) {
                throw new CoreException(CoreException::IO_OPERATION_FAILED);
            }
        } else {
            rewind($this->descriptor);
        }
        return $this->descriptor;
    }
  
    /**
     * gets file contents
     *
     * @return string
     */
    public function getContents(): string
    {
        $s = stream_get_contents($this->openStream());
        $this->size = strlen($s);
        if (!$this->mimeType) {
            if (function_exists("finfo_file")) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE | FILEINFO_PRESERVE_ATIME);
                $this->mimeType = finfo_buffer($finfo, $s);
                finfo_close($finfo);
            }
        }
        return $s;
    }
  
    public function getStream(): IStream
    {
        $s = new Stream($this->openStream());
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
    public function __toString()
    {
        return $this->URI;
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
        $s = $this->getStream();
        $out = fopen($filename, 'w');
        if (!$out) {
            throw new CoreException(CoreException::IO_OPERATION_FAILED);
        }
        while (!$s->eof()) {
            fwrite($out, $s->read(1024));
        }
        fclose($out);
        return true;
    }
}
