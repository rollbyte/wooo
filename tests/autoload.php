<?php
spl_autoload_register(function ($class) {
    if (substr($class, 0, 10) === 'wooo\tests') {
        $classPath = __DIR__ . DIRECTORY_SEPARATOR . str_ireplace('\\', DIRECTORY_SEPARATOR, substr($class, 10)) .'.php';
        include_once $classPath;
        return;
    }
    $classPath = str_ireplace('\\', DIRECTORY_SEPARATOR, $class);
    if ($php = stream_resolve_include_path($classPath.'.php')) {
        include_once $php;
    }
}, true, true);