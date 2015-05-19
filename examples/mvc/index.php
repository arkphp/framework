<?php
require __DIR__.'/../bootstrap.php';

use Ark\Framework\WebApp;

$app = new WebApp([
    'debug' => true,
]);

$app
    ->add(['GET', 'POST', 'PUT', 'DELETE'], '/{path_:[A-Za-z0-9/_-]*}', function($vars) {
        $path = $vars['_path'];
        $parts = array_filter(explode('/', $path), function($v) {
            return $v !== '';
        });

        if (count($parts) == 2) {

        } elseif (count($parts == 1)) {
            $parts[1] = ['index'];
        } elseif (count($parts) == 0) {
            $parts = ['home', 'index'];
        }

        list($controller, $action) = $parts;

        $file = __DIR__.'/controller/'.$controller.'.php';
        $class = ucfirst($controller).'Controller';
        $action = $action.'Action';

        if (!file_exists($file)) {
            $this->emit('not_found');
            return;
        }

        if (!class_exists($class)) {
            $this->emit('not_found');
            return;
        }

        $c = new $class();
        if (!method_exists($c, $action)) {
            $this->emit('not_found');
            return;
        }

        $c->$action();
    })
    ->on('not_found', function() {
        header('HTTP/1.1 404 Not Found');
        echo 'Page not found';

        return false;
    })
    ->on('exception', function() {
        echo 'exception';
        return false;
    })
    ;
$app->run();