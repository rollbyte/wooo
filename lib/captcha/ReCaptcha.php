<?php

namespace wooo\lib\captcha;

class ReCaptcha
{
  
    private $key;
  
    private $secret;
  
    public function __construct($key, $secret)
    {
        $this->key = $key;
        $this->secret = $secret;
    }
  
    public function secret(): string
    {
        return $this->secret;
    }
  
    public function key(): string
    {
        return $this->key;
    }
  
    public function verify($token): bool
    {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            http_build_query(
                [
                'secret' => $this->secret,
                'response' => $token
                ]
            )
        );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = json_decode(curl_exec($ch));
        return $response->success;
    }
}
