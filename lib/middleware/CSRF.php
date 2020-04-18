<?php

namespace wooo\lib\middleware;

use wooo\core\Token;
use wooo\core\App;
use wooo\core\DateTime;
use wooo\core\HttpMethod;

class CSRF
{
    public static function handler(string $paramName = 'csrf_token_value', $cookie = false, $timeout = null)
    {
        return function (App $app) use ($paramName, $cookie, $timeout) {
            $paramName = $app->config()->get('csrfTokenName', $paramName);
            $cookie = $app->config()->get('csrfCookie', $cookie);
            $timeout = $app->config()->get('csrfTimeout', $timeout);
            
            $token = base64_decode($cookie ?
                $app->request()->getCookie($paramName) :
                $app->request()->session()->get($paramName));

            if ($timeout) {
                $expDate = $cookie ?
                    $app->request()->getCookie($paramName . '_expires') :
                    $app->request()->session()->get($paramName . '_expires');
                if (!$expDate) {
                    $token = null;
                } else {
                    $expDate = new DateTime(intval($expDate));
                    if ($expDate->getTimestamp() < time()) {
                        $token  = null;
                    }
                }
            }

            if (!$token) {
                $token = new Token();
                if ($cookie) {
                    $app->response()->setCookie($paramName, base64_encode($token->value()));
                } else {
                    $app->request()->session()->set($paramName, base64_encode($token->value()));
                }
                if ($timeout) {
                    if ($cookie) {
                        $app->response()->setCookie($paramName . '_dt', time() + $timeout);
                    } else {
                        $app->request()->session()->set($paramName . '_dt', time() + $timeout);
                    }
                }
            } else {
                $token = new Token($token, false);
            }

            $base64token = base64_encode($token->masked());
            $app->response()->csrf_token_name = $paramName;
            $app->response()->csrf_token_value = $base64token;
            $app->response()->setHeader('csrf-token-name: ' . $paramName);
            $app->response()->setHeader('csrf-token-value: ' . $base64token);
            
            if (HttpMethod::isWriting($app->request()->getMethod())) {
                $reqToken = $app->request()->getHeader($paramName);
                if (!$reqToken) {
                    $reqToken = $app->request()->getBody()->$paramName ?? null;
                }
                if (!$reqToken) {
                    $app->response()->setStatus(403)->send('Access denied.');
                }
                
                $reqToken = new Token(base64_decode($reqToken));
                if (!hash_equals($token->value(), $reqToken->value())) {
                    $app->response()->setStatus(403)->send('Access denied.');
                }
            }
        };
    }
}
