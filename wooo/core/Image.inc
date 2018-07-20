<?php
namespace wooo\core;

class Image implements IImage {
	
	private $base;
	
	public function __construct(IFile $base) {
		$this->base = $base;
	}
	
	/**
	 * @param string $filename
	 * @param int $type
	 * @param int $width
	 * @param int $height
	 * @throws \Exception
	 */
	public function convert($filename, $type, $width = null, $height = null, $crop = false) : IImage {
		$dest = $filename;
		
		if (!FileSystem::IsAbsolute($filename)) {
			$dest = null;
		} else {
			if (file_exists($dest)) {
				unlink($dest);
			}
			FileSystem::ForceDir(dirname($dest));
		}
		
		$data = $this->getContents();
		
		
		list($orig_width, $orig_height, $orig_type, $attr) = getimagesizefromstring($data);
					
		$s_width = $width?$width:$orig_width;
		$s_height = $height?$height:$orig_height;
					
		$s_o_width = $orig_width;
		$s_o_height = $orig_height;
					
					
		if ($s_width == 0 || $s_height == 0) {
			throw new \Exception('Image is invalid');
		}
						
		if ($orig_width < $orig_height){
			$s_o_width = $orig_width;
			$s_o_height = floor($s_height*$orig_width/$s_width);
		} else {
			$s_o_height = $orig_height;
			$s_o_width = floor($s_width*$orig_height/$s_height);
		}
						
		$x = 0; $y = 0;
		if ($s_o_width < $orig_width){
			$x = floor(($orig_width - $s_o_width)/2);
		}
						
		if ($s_o_height < $orig_height){
			$y = floor(($orig_height - $s_o_height)/2);
		}
						
		$image_dest = imagecreatetruecolor($s_width, $s_height);
		$image_src = imagecreatefromstring($data);
						
		imagecopyresampled($image_dest, $image_src, 0, 0, $x, $y, $s_width, $s_height, $s_o_width, $s_o_height);
		
		if (!$dest) {
			ob_start();
		}
		switch ($type){
			case IMAGETYPE_GIF: imagegif($image_dest, $dest);break;
			case IMAGETYPE_PNG: imagepng($image_dest, $dest);break;
			default:imagejpeg($image_dest, $dest);break;
		}
		imagedestroy($image_src);
		imagedestroy($image_dest);
		if (!$dest) {
			switch ($type) {
				case IMAGETYPE_GIF: $dest = 'data://image/gif;base64,'.base64_encode(ob_get_contents());break;
				case IMAGETYPE_PNG: $dest = 'data://image/png;base64,'.base64_encode(ob_get_contents());break;
				default:$dest = 'data://image/jpeg;base64,'.base64_encode(ob_get_contents());break;
			}
			ob_end_clean();
			return new Image(new StreamFile(basename($filename), $dest));
		}
		return new Image(new LocalFile(basename($filename), $dest));
	}
	
	public function getSize(): int {
		return $this->base->getSize();
	}

	public function getName(): string {
		return $this->base->getName();
	}

	public function getMimeType(): string {
		return $this->base->getMimeType();
	}

	public function saveAs($filename): bool {
		return $this->base->saveAs($filename);
	}

	public function getContents(): string {
		return $this->base->getContents();
	}

	public function getStream() {
		return $this->base->getStream();
	}

	public function delete(): bool {
		return $this->base->delete();
	}

	public function getURI(): string {
		return $this->base->getURI();
	}
}