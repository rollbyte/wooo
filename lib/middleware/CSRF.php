<?php

namespace wooo\lib\middleware;

use wooo\core\Token;
use wooo\core\App;
use wooo\core\HttpMethod;

class CSRF
{
    public static function handler(string $paramName = 'csrf_token_value', $cookie = false)
    {
        return function (App $app) use ($paramName, $cookie) {
            $paramName = $app->config()->get('csrfTokenName', $paramName);
            $cookie = $app->config()->get('csrfCookie', $cookie);
            
            $token = $cookie ? $app->request()->getCookie($paramName) : $app->request()->session()->get($paramName);

            if (!$token) {
                $token = new Token();
                if ($cookie) {
                    $app->response()->setCookie($paramName, $token->value());
                } else {
                    $app->request()->session()->set($paramName, $token->value());
                }
            } else {
                $token = new Token($token, false);
            }
            
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
            
            $base64token = base64_encode($token->masked());
            $app->response()->csrf_token_name = $paramName;
            $app->response()->csrf_token_value = $base64token;
            $app->response()->setHeader('csrf-token-name: ' . $paramName);
            $app->response()->setHeader('csrf-token-value: ' . $base64token);
        };
    }
}
