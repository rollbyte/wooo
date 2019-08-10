<?php
namespace wooo\core;

interface ITemplateEngine
{
    function render(string $path, array $data = []): void;
}