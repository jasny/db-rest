<?php

// Custom autoloader for stubs
spl_autoload_register(function($class) {
    $parts = explode('\\', $class);
    if (join('\\', array_splice($parts, 0, 3)) !== 'Jasny\DB\REST') return;
    
    $path = __DIR__ . DIRECTORY_SEPARATOR . join(DIRECTORY_SEPARATOR, $parts) . '.php';
    if (file_exists($path)) require_once $path;
});

require_once __DIR__ . '/../vendor/autoload.php';
