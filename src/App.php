<?php
/**
 * Ark Framework
 *
 * @link http://github.com/ark/framework
 * @copyright  Liu Dong (http://codecent.com)
 * @license MIT
 */

namespace Ark\Framework;

use Pimple\Container;
use Ark\Event\EventEmitter;

/**
 * Ark app
 */
abstract class App
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
        
        $this->event = new EventEmitter();
        $this->container = new Container();

        $this->event->emit('app.before', [$this]);
        
        // exception handler
        set_exception_handler(array($this, 'handleException'));
        $this->event->on('app.exception', array($this, 'handleExceptionDefault'), 10000);

        $this->init();

        //app is ready to run
        $this->event->emit('app.ready', [$this]);
    }
    
    /**
     * Init app
     */
    abstract protected function init();

    protected function isCli(){
        return PHP_SAPI === 'cli';
    }

    /**
     * Run app
     */
    abstract public function run();

    public function handleException($exception)
    {
        $this->event->emit('app.exception', [$exception]);
    }
    
    public function handleExceptionDefault($exception)
    {
        if ($this->configs['debug']) {
            throw $exception;
        } else {
            echo 'Error occurred';
        }
    }
}