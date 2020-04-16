<?php

namespace wooo\lib\middleware;

use wooo\core\App;
use wooo\lib\transactions\TransactionManager;
use wooo\core\events\app\AppEvent;
use wooo\core\events\IEvent;
use wooo\core\HttpMethod;

class GlobalTransaction
{
    public static function handler()
    {
        return function (App $app) {
            $app->on(AppEvent::USE, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-begin')) {
                    return;
                }
                /**
                 * @var \wooo\core\App $app
                 */
                $app = $event->emitter();
                if (
                    $app->request()->getMethod() !== HttpMethod::GET &&
                    $app->request()->getMethod() !== HttpMethod::HEAD &&
                    $app->request()->getMethod() !== HttpMethod::SEARCH
                ) {
                    $event->mark('global-transaction-begin');
                    $tm->begin();
                }
            });

            $app->on(AppEvent::ERROR, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-rollback')) {
                    return;
                }
                if ($tm->rollback()) {
                    $event->mark('global-transaction-rollback');
                }
            });
            
            $app->on(AppEvent::EXIT, function (IEvent $event, array $data, TransactionManager $tm) {
                if ($event->hasMark('global-transaction-commit')) {
                    return;
                }
                if ($tm->commit()) {
                    $event->mark('global-transaction-commit');
                }
            });
        };
    }
}
