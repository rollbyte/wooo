<?php

namespace wooo\lib\middleware;

use wooo\core\App;
use wooo\core\HttpMethod;

class CORS
{
    public static function handler(array $domains = [])
    {
        return function (App $app) use ($domains) {
            $domains = array_merge($app->config()->get('CORS', []), $domains);
            if ($h = $app->request()->getHeader('Origin')) {
                if (empty($domains) || in_array($h, $domains)) {
                    $app->response()
                        ->setHeader('Access-Control-Allow-Origin: ' . empty($domain) ? '*' : join(' ', $domains))
                        ->setHeader('Access-Control-Allow-Methods: *') // TODO
                        ->setHeader('Access-Control-Allow-Headers: *'); // TODO
                    if ($app->request()->getMethod() === HttpMethod::OPTIONS) {
                        $app->response()->setStatus(200)->send('');
                    }
                } else {
                    $app->response()->setStatus(403)->send('Access denied due to CORS policy');
                }
            }
        };
    }
}
