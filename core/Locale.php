<?php

/**
 * @category Wooo
 * @package  Wooo
 * @license  MIT
 * @link     http://www.dalintek.ru
 * @author   Dan Krasilnikov <krasilneg@yandex.ru>
 *
 * Class of system locale object
 */

namespace wooo\core;

/**
 * @category Wooo
 * @package  Wooo
 * @license  MIT
 * @link     http://www.dalintek.ru
 *
 * Basic locale implementation
 */
class Locale
{
  
    private $locale;
  
    private $shortDateFormater;
  
    private $fullDateFormater;
  
    public function __construct(string $locale, ?string $tz = null)
    {
        $this->locale = $locale;
        $this->fullDateFormater =
            new \IntlDateFormatter(
                $this->locale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::MEDIUM,
                $tz
            );
        $this->shortDateFormater =
            new \IntlDateFormatter(
                $this->locale,
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
                $tz
            );
    }
  
    public function name(): string
    {
        return $this->locale;
    }
  
    public function formatDate(\DateTime $date, $noTime = false): string
    {
        if ($noTime) {
            return $this->shortDateFormater->format($date);
        }
        return $this->fullDateFormater->format($date);
    }
  
    public function parseDate(string $v, $withTime = true): ?\DateTime
    {
        $ts = $this->fullDateFormater->parse($v);
        if ($ts === false) {
            $ts = $this->shortDateFormater->parse($v);
        }
        if ($ts === false) {
            return null;
        }
        $d = new \DateTime('@' . $ts);
        if (!$withTime) {
            $d->setTime(0, 0, 0, 0);
        }
        return $d;
    }
  
    public function dateTimeFormat(): string
    {
        return $this->fullDateFormater->getPattern();
    }
  
    public function dateFormat(): string
    {
        return $this->shortDateFormater->getPattern();
    }
  
    public function language(): string
    {
        return locale_get_primary_language($this->locale);
    }
}
