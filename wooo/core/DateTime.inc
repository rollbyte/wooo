<?php
namespace wooo\core;

class DateTime extends \DateTime implements \JsonSerializable {
	
	public function jsonSerialize() {
		return $this->format(\DateTime::ISO8601);
	}
	
	public function __toString() {
		return $this->format('YmdHisO');
	}
}