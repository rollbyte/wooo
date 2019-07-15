<?php

namespace wooo\core;

class DateTime extends \DateTime implements \JsonSerializable
{
  
    public function jsonSerialize(): string
    {
        return $this->format(\DateTime::ISO8601);
    }
  
    public function __toString(): string
    {
        $d = clone $this;
        $d->setTimezone(new \DateTimeZone('UTC'));
        return $d->format('YmdHis');
    }
    
    public function __construct($time = null, ?\DateTimeZone $timezone = null)
    {
        if ($time instanceof \DateTime) {
            parent::__construct();
            $this->setTimestamp($time->getTimestamp());
            $this->setTimezone($timezone ?? $time->getTimezone());
        } else {
            parent::__construct($time, $timezone);
        }
    }
}
