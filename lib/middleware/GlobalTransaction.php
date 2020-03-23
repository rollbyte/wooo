<?php

namespace wooo\lib\middleware;

use wooo\core\App;
use wooo\lib\transactions\TransactionManager;
use wooo\core\events\app\AppEvent;
use wooo\core\events\IEvent;

class GlobalTransaction
{
    public static function handler()
    {
        return function (App $app) {
            $app->on(AppEvent::USE, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-begin')) {
                    return;
                }
                $event->mark('global-transaction-begin');
                $tm->begin();
            });

            $app->on(AppEvent::ERROR, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-rollback')) {
                    return;
                }
                $event->mark('global-transaction-rollback');
                $tm->rollback();
            });
            
            $app->on(AppEvent::EXIT, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-commit')) {
                    return;
                }
                $event->mark('global-transaction-commit');
                $tm->commit();
            });
        };
    }
}
