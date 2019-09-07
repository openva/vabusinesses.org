<?php

function __autoload_libraries($name)
{
    if (php_sapi_name() == 'cli')
    {
        $includes_dir = __DIR__ . '/';
    }
    else
    {
        $includes_dir = realpath($_SERVER['DOCUMENT_ROOT']) . '/includes/';
    }

    if (file_exists($includes_dir . 'class.' . $name . '.php') === TRUE)
    {
        include 'class.' . $name . '.php';
        return TRUE;
    }
}

spl_autoload_register('__autoload_libraries');
