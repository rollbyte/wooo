<?php

namespace wooo\core;

interface ITemplateEngine
{
    public function render(string $path, array $data = []): void;
}
