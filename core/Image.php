<?php

namespace wooo\core;

use wooo\core\exceptions\CoreException;

class Image implements IImage
{
  
    private $base;
  
    public function __construct(IFile $base)
    {
        $this->base = $base;
    }
    
    public function origin(): IFile
    {
        return $this->base;
    }
  
    /**
     * @throws \wooo\core\exceptions\CoreException
     */
    public function convert(int $type, $width = null, $height = null, $crop = false, $filename = null): IFile
    {
        if ($filename) {
            $name = basename($filename);
            $dest = $filename;
        } else {
            $name = pathinfo($this->base->getName(), PATHINFO_FILENAME) . image_type_to_extension($type);
            $dest = null;
        }
        
        if ($dest) {
            if (!FileSystem::IsAbsolute($dest)) {
                $dest = null;
            } else {
                if (file_exists($dest)) {
                    unlink($dest);
                }
                FileSystem::ForceDir(dirname($dest));
            }
        }
            
        $data = $this->getContents();
    
        list($orig_width, $orig_height, $orig_type, $attr) = getimagesizefromstring($data);
    
        $s_width = $width ? $width : $orig_width;
        $s_height = $height ? $height : $orig_height;
    
        $s_o_width = $orig_width;
        $s_o_height = $orig_height;
    
        if ($s_width == 0 || $s_height == 0) {
            throw new CoreException(CoreException::INVALID_IMAGE);
        }
    
        if ($orig_width < $orig_height) {
            $s_o_width = $orig_width;
            $s_o_height = floor($s_height * $orig_width / $s_width);
        } else {
            $s_o_height = $orig_height;
            $s_o_width = floor($s_width * $orig_height / $s_height);
        }
    
        $x = 0;
        $y = 0;
        if ($s_o_width < $orig_width) {
            $x = floor(($orig_width - $s_o_width) / 2);
        }
    
        if ($s_o_height < $orig_height) {
            $y = floor(($orig_height - $s_o_height) / 2);
        }
    
        $image_dest = imagecreatetruecolor($s_width, $s_height);
        $image_src = imagecreatefromstring($data);
    
        imagecopyresampled($image_dest, $image_src, 0, 0, $x, $y, $s_width, $s_height, $s_o_width, $s_o_height);
    
        if (!$dest) {
            ob_start();
        }
        switch ($type) {
            case IMAGETYPE_GIF:
                imagegif($image_dest, $dest);
                break;
            case IMAGETYPE_PNG:
                imagepng($image_dest, $dest);
                break;
            case IMAGETYPE_JPEG:
                imagejpeg($image_dest, $dest);
                break;
            default:
                throw new CoreException(CoreException::INVALID_IMAGE_TYPE);
                break;
        }
        $f = null;
        $mime = image_type_to_mime_type($type);
        if (!$dest) {
            $dest = 'data://' . $mime . ';base64,' . base64_encode(ob_get_contents());
            ob_end_clean();
            $f = new StreamFile($name, $dest, null, $mime);
        } else {
            $f = new LocalFile($name, $dest, $mime);
        }
        imagedestroy($image_src);
        imagedestroy($image_dest);
        return $f;
    }
  
    public function getSize(): ?int
    {
        return $this->base->getSize();
    }
  
    public function getName(): string
    {
        return $this->base->getName();
    }
  
    public function getMimeType(): ?string
    {
        return $this->base->getMimeType();
    }
  
    public function saveAs($filename): bool
    {
        return $this->base->saveAs($filename);
    }
  
    public function getContents(): string
    {
        return $this->base->getContents();
    }
  
    public function getStream(): IStream
    {
        return $this->base->getStream();
    }
}
