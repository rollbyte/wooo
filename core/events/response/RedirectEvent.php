<?php

namespace wooo\core\events\response;

use wooo\core\Response;

class RedirectEvent extends ResponseEvent
{
    protected function __construct(Response $response, string $url)
    {
        parent::__construct(ResponseEvent::REDIRECT, $response, ['url' => $url]);
    }
    
    public function url()
    {
        return $this->data['url'];
    }    
}