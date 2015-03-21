<?php
/**
 * Ark Framework
 *
 * @author Liu Dong <ddliuhb@gmail.com>
 * @license MIT
 */

namespace Ark\Framework;

use Pimple\Container;
use Ark\Event\EventEmitter;

/**
 * Ark app
 */
abstract class BaseApp implements \ArrayAccess
{
    public $container;

    public $configs;

    public static $instance;

    public $event;

    public function __construct(array $configs)
    {
        self::$instance = $this;
        $this->configs = $configs;
        if ($configs['debug']) {
            ini_set('display_errors', true);
            error_reporting(E_ALL^E_NOTICE);
        } else {
            ini_set('display_errors', false);
        }
        
        $event = $this->event = new EventEmitter();
        $this->container = new Container();
        
        // exception handler
        set_exception_handler(function() use ($event) {
            $event->emit('exception');
        });

        // Set default handlers
        $this->on('exception', array($this, 'onException'), 10000);

        $this->on('dispatch', [$this, 'onDispatch'], 10000);
    }

    public function on($event, $callback, $priority = null) {
        $this->event->on($event, $callback, $priority);

        return $this;
    }

    public function emit($event, $args = []) {
        $this->event->emit($event, $args);
    }
    
    /**
     * Run app
     */
    public function run() {
        $this->emit('init', [$this]);
        $this->emit('dispatch', [$this]);
    }
    
    public function onException($exception)
    {
        if ($this->configs['debug']) {
            throw $exception;
        } else {
            echo 'Error occurred';
        }
    }

    abstract function onDispatch($app);

    public function offsetExists($offset) {
        return isset($this->container[$offset]);
    }

    public function offsetGet($offset) {
        return $this->container[$offset];
    }

    public function offsetSet($offset, $value) {
        $this->container[$offset] = $value;
    }

    public function offsetUnset($offset) {
        unset($this->container[$offset]);
    }
}