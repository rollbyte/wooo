<?php
namespace wooo\core;

interface IFile {
	/**
	 * @return string
	 */
	public function getName(): string;
	
	/**
	 * @return string
	 */
	public function getMimeType(): string;
	
	/**
	 * @return integer
	 */
	public function getSize(): int;
	
	/**
	 * @return string
	 */
	public function getURI(): string;
	
	/**
	 * @return string
	 */
	public function getContents(): string;
	
	/**
	 * @return resource
	 */
	public function getStream();
	
	/**
	 * @param string $filename
	 * @return boolean
	 */
	public function saveAs($filename): bool;
	
	/**
	 * @return boolean
	 */
	public function delete(): bool;
}