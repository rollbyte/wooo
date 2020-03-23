<?php

namespace wooo\core\events\response;

use wooo\core\Response;

class RenderEvent extends ResponseEvent
{
    protected function __construct(Response $response, string $tpl, array $vars = [])
    {
        parent::__construct(ResponseEvent::RENDER, $response, ['tpl' => $tpl, 'vars' => $vars]);
    }
    
    public function template()
    {
        return $this->data['tpl'];
    }
    
    public function variables()
    {
        return $this->data['vars'];
    }
}
