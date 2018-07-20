<?php
namespace wooo\core;

class Locale {
	
	private $locale;
	
	private $shortDateFormater;
	
	private $fullDateFormater;
	
	public function __construct($locale) {
		$this->locale = $locale;
		$this->fullDateFormater = new \IntlDateFormatter($this->locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::MEDIUM);
		$this->shortDateFormater = new \IntlDateFormatter($this->locale, \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
	}
	
	public function Name() {
		return $this->locale;
	}
	
	public function formatDate(\DateTime $date, $noTime) {
		if ($noTime) {
			return $this->shortDateFormater->format($date);
		}
		return $this->fullDateFormater->format($date);
	}
	
	public function parseDate($v, $withTime) {
		if ($withTime) {
			return new \DateTime('@'.$this->fullDateFormater->parse($v));
		}
		return new \DateTime('@'.$this->shortDateFormater->parse($v));
	}
	
	public function dateTimeFormat() {
		return $this->fullDateFormater->getPattern();
	}
	
	public function dateFormat() {
		return $this->shortDateFormater->getPattern();
	}
	
	public function shortDateFormat() {
		return $this->tinyDateFormater->getPattern();
	}
	
	public function language() {
		return locale_get_primary_language($this->locale);
	}
}