<?php
namespace wooo\core;

class StreamFile implements IFile {
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
	protected $URI;
	
	/**
	 * @var mixed
	 */
	protected $descriptor;
	/**
	 * @var int file size
	 */
	protected $size;
	
	
	/**
	 * constructor. fills file object attributes.
	 * @param string $name file name
	 * @param string $uri stream uri
	 * @param mixed $sd stream descriptor
	 * @param string $type optional mime type
	 * @param int $size optional size
	 */
	public function __construct(string $name, string $uri, $sd = null, string $type = null, int $size = null) {
		$this->name = $name;
		$this->mimeType = $type;
		$this->URI = $uri;
		$this->size = $size ? $size : 0;
		$this->descriptor = $sd ? $sd : false;
	}
	
	/**
	 * gets file contents
	 * @return string
	 */
	public function getContents(): string {
		return stream_get_contents($this->getStream());
	}
	
	public function getStream() {
		if (!$this->descriptor) {
			$this->descriptor = fopen($this->URI, 'rb');
		} else {
			rewind($this->descriptor);
		}
		return $this->descriptor;
	}
	
	public function getSize(): int {
		return $this->size;
	}
	
	/**
	 * file string representation. by default evaluates to storage path.
	 * @return string
	 */
	public function __toString() {
		return $this->URI;
	}
	
	public function getName(): string {
		return $this->name;
	}
	
	public function getURI(): string {
		return $this->URI;
	}
	
	public function getMimeType(): string {
		return $this->mimeType;
	}
	
	public function saveAs($filename): bool {
		if (!FileSystem::isAbsolute($filename)) {
			throw new \Exception('File path should be absolute!');
		}
		FileSystem::forceDir(dirname($filename));
		file_put_contents($filename, $this->getContents());
		return true;
	}
	
	public function delete(): bool {
		return true;
	}
}