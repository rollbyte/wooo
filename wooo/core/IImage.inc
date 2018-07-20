<?php
namespace wooo\core;

interface IImage extends IFile {
	public function convert($filename, $type, $width = null, $height = null, $crop = false) : IImage;
}