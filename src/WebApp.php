<?php
/**
 * Ark Framework
 *
 * @author Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Framework;

use FastRoute;

/**
 * Web app
 */
class WebApp extends BaseApp
{
    protected $routes = [];
    
    public function __construct($configs) {
        parent::__construct($configs);

        $this->on('not_found', [$this, 'onNotFound'], 10000);

    }

    public function onNotFound()
    {
        header('HTTP/1.1 404 Not Found');
        echo 'Not Found';
    }

    public function onException($exception)
    {
        header('HTTP/1.1 500 Internal Server Error');

        if ($this->configs['debug']) {
            throw $exception;
        } else {
            echo 'Internal Server Error';
        }
    }

    public function onDispatch($app) {
        $routes = $this->routes;
        $dispatcher = FastRoute\simpleDispatcher(function(FastRoute\RouteCollector $r) use ($routes) {
            foreach ($routes as $route) {
                $r->addRoute($route[0], $route[1], $route[2]);
            }
        });

        $method = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];
        $path = parse_url($uri, PHP_URL_PATH);

        $routeInfo = $dispatcher->dispatch($method, $path);

        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::NOT_FOUND:
                $this->emit('not_found');
                break;
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                header('HTTP/1.1 405 Method Not Allowed');
                break;
            case FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                if ($vars) {
                    call_user_func($handler, $vars);
                } else {
                    call_user_func($handler);
                }
                break;
        }
    }

    public function add($method, $rule, $handler) {
        $this->routes[] = [$method, $rule, $handler];

        return $this;
    }

    public function __call($method, $params) {
        array_unshift($params, strtoupper($method));
        return call_user_func_array([$this, 'add'], $params);
    }
}