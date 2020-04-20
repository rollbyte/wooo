<?php

namespace wooo\lib\middleware;

use wooo\core\Token;
use wooo\core\App;
use wooo\core\DateTime;
use wooo\core\HttpMethod;

class CSRF
{
    private static function renewToken(App $app, $paramName, $cookie, $timeout): Token
    {
        if ($cookie) {
            $app->response()->setCookie($paramName, time() + $timeout);
        } else {
            $app->request()->session()->set($paramName, time() + $timeout);
        }
        return new Token();
    }

    private static function setToken(App $app, ?Token $token, $paramName, $cookie, $timeout)
    {
        $expParamName = $paramName . '_expires';

        if ($timeout) {
            $expDate = $cookie ?
                $app->request()->getCookie($expParamName) :
                $app->request()->session()->get($expParamName);
            if (!$expDate) {
                $token = self::renewToken($app, $expParamName, $cookie, $timeout);
            } else {
                $expDate = new DateTime("@$expDate");
                if ($expDate->getTimestamp() < time()) {
                    $token = self::renewToken($app, $expParamName, $cookie, $timeout);
                }
            }
        }

        if (!$token) {
            $token = new Token();
        }

        $base64token = base64_encode($token->masked());
        $app->response()->csrf_token_name = $paramName;
        $app->response()->csrf_token_value = $base64token;
        $app->response()->setHeader('csrf-token-name: ' . $paramName);
        $app->response()->setHeader('csrf-token-value: ' . $base64token);

        if ($cookie) {
            $app->response()->setCookie($paramName, base64_encode($token->value()));
        } else {
            $app->request()->session()->set($paramName, base64_encode($token->value()));
        }
    }

    public static function handler(string $paramName = 'csrf_token_value', $cookie = false, $timeout = null)
    {
        return function (App $app) use ($paramName, $cookie, $timeout) {
            $paramName = $app->config()->get('csrfTokenName', $paramName);
            $cookie = $app->config()->get('csrfCookie', $cookie);
            $timeout = $app->config()->get('csrfTimeout', $timeout);
            
            $token = base64_decode($cookie ?
                $app->request()->getCookie($paramName) :
                $app->request()->session()->get($paramName));

            if (!$token) {
                if (HttpMethod::isWriting($app->request()->getMethod())) {
                    $app->response()->setStatus(403)->send('Access denied.');
                }
                self::setToken($app, null, $paramName, $cookie, $timeout);
                return;
            }

            $token = new Token($token, false);
            
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

            self::setToken($app, $token, $paramName, $cookie, $timeout);
        };
    }
}
