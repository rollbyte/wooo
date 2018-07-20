<?php
namespace wooo\core;

final class UploadedFile extends LocalFile {
	 
	private $error = false;
	
	private $stored = false;
	 
	public function uploadError(){
		return $this->error;
	}
	 
	/**
	 * constructor
	 * @param array $file php uploaded file structure
	 */
	public function __construct(array $file){
		if ($file["error"] != UPLOAD_ERR_OK || filesize($file["tmp_name"]) == 0){
			$this->error = $file["error"];
			return;
		}
		parent::__construct($file["name"], $file["tmp_name"], $file["type"], $file["size"]);
	}
	
	/**
	 * saves file to specified destination.
	 */
	public function saveAs($filename): bool {
		if ($this->stored) {
			return parent::saveAs($filename);
		}
		$e = null;
		switch ($this->error){
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
			case UPLOAD_ERR_PARTIAL:
			case UPLOAD_ERR_NO_FILE:$e = new \Exception('Upload error! No file uploaded.', $this->error);break;
			case UPLOAD_ERR_NO_TMP_DIR:$e = new \Exception('Upload error! TMP path not set.',$this->error);break;
			case UPLOAD_ERR_CANT_WRITE:$e = new \Exception('Upload error! Can not write file to disk.',$this->error);break;
			case UPLOAD_ERR_EXTENSION:$e = new \Exception('Upload Error! PHP-extension stopped file upload.',$this->error);break;
		}
		if (!is_null($e))
			throw $e;
	
		if (!FileSystem::isAbsolute($filename))
			throw new \Exception('File path should be absolute!');
	
		FileSystem::forceDir(dirname($filename));
	
		if (move_uploaded_file($this->path, $filename)){
			$this->path = $filename;
			$this->stored = true;
			return true;
		}
		return false;
	}
}