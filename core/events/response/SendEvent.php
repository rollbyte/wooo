<?php

namespace wooo\core\events\response;

use wooo\core\Response;

class SendEvent extends ResponseEvent
{
    protected function __construct(Response $response, $data)
    {
        parent::__construct(ResponseEvent::SEND, $response, ['contents' => $data]);
    }
    
    public function contents()
    {
        return $this->data['contents'];
    }
}
