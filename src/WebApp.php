<?php
/**
 * Ark Framework
 *
 * @link http://github.com/ark/framework
 * @copyright  Liu Dong (http://codecent.com)
 * @license MIT
 */

namespace Ark\Framework;

/**
 * Web app
 */
class WebApp extends App
{

    public function __construct($configs){
        parent::__construct($configs);
    }
    
    /**
     * {@inheritdoc}
     */
    protected function init(){
        $this->event->on('app.404', array($this, 'handle404Default'), 10000);
    }

    public function handle404Default($event)
    {
        header('HTTP/1.1 400 Not Found');
        echo 'Not Found';
    }

    public function handleExceptionDefault($exception)
    {
        header('HTTP/1.1 500 Internal Server Error');

        if ($this->configs['debug']) {
            echo $exception;
        } else {
            echo 'Internal Server Error';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function run(){
        $this->dispatch();
    }
    
    /**
     * Dispatch
     * @param array $r
     */
    public function dispatch(){
        echo 'abc';
    }
}