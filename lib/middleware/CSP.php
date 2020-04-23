<?php

namespace wooo\lib\middleware;

use wooo\core\App;

class CSP
{
    private static function serializeValue(array $value, $level = 0)
    {
        return implode($level > 0 ? ' ' : ';', array_map(function ($item) use ($level) {
            return is_array($item) ? self::serializeValue($item, $level + 1) : $item;
        }, $value));
    }

    public static function handler(array $config = [])
    {
        return function (App $app) use ($config) {
            if (!$app->request()->isAjax()) {
                $value = array_merge_recursive(['default-src' => '\'self\''], $app->config()->get('CSP', []), $config);
                $app->response()->setHeader('Content-Security-Policy: ' . self::serializeValue($value));
            }
        };
    }
}
