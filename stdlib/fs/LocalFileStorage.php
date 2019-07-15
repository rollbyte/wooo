<?php

namespace wooo\stdlib\fs;

use wooo\stdlib\fs\interfaces\IFileStorage;
use wooo\core\IFile;
use wooo\core\App;
use wooo\core\FileSystem;
use wooo\core\LocalFile;
use wooo\core\Image;

class LocalFileStorage implements IFileStorage
{
  
    private $storagePath = null;
  
    public function __construct(App $app, $path = 'files')
    {
        if (!FileSystem::isAbsolute($path)) {
            $appPath = $app->appPath();
            if (!$appPath) {
                throw new FileStorageException(FileStorageException::LOCAL_NO_APP_PATH);
            }
            $path = $appPath . DIRECTORY_SEPARATOR . $path;
        }
        $this->storagePath = $path;
    }
  
    public function getStoragePath()
    {
        return $this->storagePath;
    }
  
    /**
     * {@inheritdoc}
     *
     * @see \wooo\stdlib\fs\interfaces\IFileStorage::get()
     */
    public function get(string $id): ?IFile
    {
        if ($id) {
            $fn = $this->storagePath . DIRECTORY_SEPARATOR . $id;
            if (file_exists($fn) && is_file($fn)) {
                $result = new LocalFile(pathinfo($fn, PATHINFO_BASENAME), $fn);
                if (explode('/', $result->getMimeType())[0] === 'image') {
                    $result = new Image($result);
                }
                return $result;
            }
        }
        return null;
    }
  
    /**
     * {@inheritdoc}
     *
     * @see \wooo\stdlib\fs\interfaces\IFileStorage::delete()
     */
    public function delete(string $id): void
    {
        $fn = $this->storagePath . DIRECTORY_SEPARATOR . $id;
        if (file_exists($fn)) {
            unlink($fn);
        }
    }
  
    /**
     * {@inheritdoc}
     *
     * @see \wooo\stdlib\fs\interfaces\IFileStorage::accept()
     */
    public function accept(IFile $file, $path = null): string
    {
        if ($path) {
            $path = $path . DIRECTORY_SEPARATOR;
        }
        $id = date('Y' . DIRECTORY_SEPARATOR . 'm' . DIRECTORY_SEPARATOR . 'd') .
              DIRECTORY_SEPARATOR . $path . $file->getName();
        $fn = $this->storagePath . DIRECTORY_SEPARATOR . $id;
        $file->saveAs($fn);
        return $id;
    }
}
