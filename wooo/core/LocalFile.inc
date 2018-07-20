<?php
namespace wooo\core;

class LocalFile implements IFile {
	/**
	 * @var string file name
	 */
	protected $name;
	/**
	 * @var string file mime type
	 */
	protected $mimeType;
	/**
	 * @var string local path of file
	 */
	protected $path;
	/**
	 * @var int file size
	 */
	protected $size;
	
	
	/**
	 * constructor. fills file object attributes.
	 * @param string $name file name
	 * @param string $path file path
	 * @param string $type optional mime type
	 * @param int $size optional size
	 */
	public function __construct($name, $path = null, $type = null, $size = null) {
		$this->name = $name;
		$this->mimeType = $type;
		if (!$this->mimeType){
			if (function_exists("finfo_file") && !parse_url($path, PHP_URL_SCHEME) && file_exists($path)){
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$this->mimeType = finfo_file($finfo, $path);
				finfo_close($finfo);
			}
		}
		
		if (!$path) {
			$path = $name;
			$name = basename($path);
		}
		
		if ($path == null || !FileSystem::isAbsolute($path)) {
			throw new \Exception('Path should be absolute');
		}
		$this->path = $path;
		$this->size = $size;
	}
	
	/**
	 * gets file contents
	 * @return string
	 */
	public function getContents(): string {
		return file_get_contents($this->path);
	}
	
	public function getStream() {
		return fopen($this->path, 'r');
	}
	
	public function getSize(): int {
		return $this->size;
	}
	
	/**
	 * file string representation. by default evaluates to storage path.
	 * @return string
	 */
	public function __toString(): string {
		return $this->getPath();
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function getURI(): string {
		if (!parse_url($this->path, PHP_URL_SCHEME)) {
			return 'file://'.$this->path;
		}
		return $this->path;
	}

	public function getMimeType(): string {
		return $this->mimeType;
	}

	public function saveAs($filename): bool {
		if (!FileSystem::isAbsolute($filename)) {
			throw new \Exception('File path should be absolute!');
		}
		FileSystem::forceDir(dirname($filename));
		copy($this->path, $filename);
		return true;
	}
	
	public function delete(): bool {
		unlink($this->path);
		return true;
	}

}