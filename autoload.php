<?php
spl_autoload_register(function ($class) {
    if (strpos($class, 'wooo\\') == 0) {
        $php = realpath(__DIR__ . DIRECTORY_SEPARATOR .
            str_ireplace('\\', DIRECTORY_SEPARATOR, substr($class, 5)) . '.php');
        if (file_exists($php)) {
            include_once $php;
        }
    }
}, true, true);