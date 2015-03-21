<?php
require __DIR__.'/bootstrap.php';

use Ark\Framework\WebApp;

$configs = require(__DIR__.'/config.php');
$app = new WebApp([
    'debug' => true,
]);
$app
    ->get('/', function() {
        echo 'Home';
    })
    ->get('/exception', function() {
        throw new Exception('Error occured');
    })
    ->get('/hello.html', function() {
        echo 'Hello world!';
    })
    ->on('not_found', function() {
        header('HTTP/1.1 Not Found');
        echo 'Page not found';

        return false;
    })
    ->on('exception', function() {
        echo 'exception';
        return false;
    })
    ;
$app->run();