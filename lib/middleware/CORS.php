<?php

namespace wooo\lib\middleware;

use wooo\core\App;

class CORS
{
    public static function handler(array $domains = [])
    {
        return function (App $app) use ($domains) {
            $domains = array_merge($app->config()->get('CORS', []), $domains);
            if ($h = $app->request()->getHeader('Origin')) {
                if (in_array($h, $domains)) {
                    array_walk($domains, function (&$domain) {
                        $domain = 'http://' . $domain;
                    });
                    $app->response()
                        ->setHeader('Access-Control-Allow-Origin: ' . join(' ', $domains))
                        ->setStatus(200)
                        ->send('');
                }
            }
        };
    }
}
