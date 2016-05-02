<?php

spl_autoload_register(function ($class) {
    // base directory for the namespace prefix
    $baseDirs = array(__DIR__.'/../src/');


    $fileName = str_replace('\\', '/', $class).'.php';
    foreach ($baseDirs as $baseDir) {
        // replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = $baseDir.$fileName;

        // if the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
});