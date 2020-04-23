<?php

namespace wooo\lib\middleware;

use wooo\core\App;
use wooo\core\Token;

class CSP
{
    private static $NONCES = [];

    private static function serializeValue(array $value, $level = 0)
    {
        return implode($level > 0 ? ' ' : ';', array_map(function ($item) use ($level) {
            if (is_array($item)) {
                return self::serializeValue($item, $level + 1);
            }
            if ($item === 'nonce-') {
                $nonce = bin2hex(random_bytes(10));
                self::$NONCES[] = $nonce;
                return 'nonce-' . $nonce;
            }
            return $item;
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

    public static function getNonce(): string
    {
        if (empty(self::$NONCES)) {
            throw new \Exception('No nonces where generated for inline scripts.');
        }
        return array_shift(self::$NONCES);
    }
}
