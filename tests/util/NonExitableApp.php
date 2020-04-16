<?php
namespace wooo\tests\util;

use wooo\core\App;
use wooo\core\events\app\AppEvent;

class NonExitableApp extends App
{
    public function exit()
    {
        $this->raise(
            new class ($this) extends AppEvent
            {
                public function __construct(App $app)
                {
                    parent::__construct(AppEvent::EXIT, $app);
                }
            }
        );
    }
}