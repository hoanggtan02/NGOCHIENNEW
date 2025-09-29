<?php
    define('ECLO', true);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
    ob_start();
    session_start();
    require __DIR__ . '/../vendor/autoload.php';
    require_once __DIR__ . '/../config/bootstrap.php';
    $app->run();
    // echo '<pre>'.json_encode($app->log() , JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).'</pre>';