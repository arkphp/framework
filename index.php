<?php
require __DIR__.'/bootstrap.php';

use Ark\Framework\WebApp;

$configs = require(__DIR__.'/config.php');
$app = new WebApp($configs);
$app->run();