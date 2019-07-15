<?php

namespace wooo\core;

use wooo\core\exceptions\UploadException;
use wooo\core\exceptions\CoreException;

class UploadedFile extends LocalFile
{
  
    private $error = false;
  
    private $stored = false;
  
    public function uploadError()
    {
        return $this->error;
    }
  
    /**
     * constructor
     *
     * @param array $file
     *          php uploaded file structure
     */
    public function __construct(array $file)
    {
        if (isset($file['error']) && ($file['error'] != UPLOAD_ERR_OK || filesize($file['tmp_name']) == 0)) {
            $this->error = $file['error'];
            return;
        }
        parent::__construct(
            $file['name'] ?? null,
            $file['tmp_name'] ?? null,
            $file['type'] ?? null,
            $file['size'] ?? null
        );
    }
    
    protected function moveUploaded($dest)
    {
        return move_uploaded_file($this->path, $dest);
    }
  
    /**
     * saves file to specified destination.
     */
    public function saveAs($filename): bool
    {
        if ($this->stored) {
            return parent::saveAs($filename);
        }

        if ($this->error) {
            throw new UploadException($this->error);
        }
    
        if (!FileSystem::isAbsolute($filename)) {
            throw new CoreException(CoreException::PATH_NOT_ABSOLUTE);
        }
    
        FileSystem::forceDir(dirname($filename));
    
        if ($this->moveUploaded($filename)) {
            $this->path = $filename;
            $this->stored = true;
            return true;
        }
        return false;
    }
}
