<?php
/**
 * @copyright Dong <ddliuhb@gmail.com>
 * @licence http://maxmars.net/license/MIT
 */

define('ARK_MICROTIME', microtime(true));
define('ARK_TIMESTAMP', round(ARK_MICROTIME));

//Path defination
define( 'ARK_DIR' , dirname(__FILE__));

class Ark
{
    public static $configs;

    /**
     * Autoload framework
     */
    public static function autoload(){
        static $registered;
        if(null === $registered){
            $registered = true;
            //autoload
            spl_autoload_register('ArkAutoload::load');

            //register ark classes
            ark_autoload_class(array(
                'ArkView' => ARK_DIR.'/view.php',
                'ArkViewHelper' => ARK_DIR.'/view.php',

                'ArkController' => ARK_DIR.'/controller.php',

                'ArkPagination' => ARK_DIR.'/pagination.php',

                'ArkCacheBase' => ARK_DIR.'/cache.php',
                'ArkCacheArray' => ARK_DIR.'/cache.php',
                'ArkCacheFile' => ARK_DIR.'/cache.php',
                'ArkCacheAPC' => ARK_DIR.'/cache.php',
                'ArkCacheMemcache' => ARK_DIR.'/cache.php',

                'ArkResponse' => ARK_DIR.'/http.php',
                'ArkRequest' => ARK_DIR.'/http.php',

                'ArkLoggerBase' => ARK_DIR.'/logger.php',
                'ArkLoggerFile' => ARK_DIR.'/logger.php',
            ));
        }
    }
}

/**
 * Ark app
 */
abstract class ArkApp
{
    public function __construct(){
        //autoload
        Ark::autoload();
        
        //path definations
        if(!defined('APP_DIR')){
            define('APP_DIR', $this->getAppDir());
        }
        if(!defined('SOURCE_DIR')){
            define('SOURCE_DIR', $this->getSourceDir());
        }
        if(!defined('VENDOR_DIR')){
            define('VENDOR_DIR', $this->getVendorDir());
        }       
        
        //get configuration
        Ark::$configs = $this->getConfigs();

        if(isset(Ark::$configs['timezone'])){
            date_default_timezone_set(Ark::$configs['timezone']);
        }
        
        //Setup default services and events
        if(!isset(Ark::$configs['services']['view'])){
            ark()->register('view', array(
                'class' => 'ArkView',
                'params' => array(
                    array(
                        'dir' => APP_DIR.'/source/view',
                        'extract' => true,
                        //'ext' => '.php',
                    )
                )
            ));
        }
        
        //autoload custom classes
        if(isset(Ark::$configs['autoload']['dir'])){
            foreach(Ark::$configs['autoload']['dir'] as $dir){
                ark_autoload_dir($dir);
            }
        }
        if(isset(Ark::$configs['autoload']['class'])){
            ark_autoload_class(Ark::$configs['autoload']['class']);
        }
        
        $this->init();
        
        //app is ready
        ark('event')->trigger('ark.ready');
    }
    
    /**
     * Init app
     */
    abstract protected function init();
    
    public function getAppDir(){
        return ARK_DIR.'/../../../..';
    }
    
    public function getSourceDir(){
        return $this->getAppDir().'/source';
    }
    
    public function getVendorDir(){
        return $this->getSourceDir().'/vendor';
    }
    
    public function getConfigFile(){
        return $this->getSourceDir().'/config.php';
    }
    
    public function getConfigs(){
        $configs = include($this->getSourceDir().'/config.php');
        return is_array($configs)?$configs:array();
    }

    /**
     * Run app
     */
    abstract public function run();
}

/**
 * Web app
 */
class ArkAppWeb extends ArkApp
{
    public function __construct(){
        if($_SERVER['REMOTE_ADDR'] === '127.0.0.1'){
            error_reporting(E_ALL^E_NOTICE);
        }
        else{
            error_reporting(0);
        }
        
        parent::__construct();

        //parse request
        $q = ark_parse_query_path();
        define('APP_URL',ark('request')->getSchemeAndHttpHost().$q['base'].'/');
    }
    
    /**
     * {@inheritdoc}
     */
    protected function init(){
        ark('event')->bind('ark.404', 'ark_404');
    }
    
    /**
     * {@inheritdoc}
     */
    public function run(){
        $q = ark_parse_query_path();
        if(!$r = ark_route($q['path'], ark_config('route'))){
            ark('event')->trigger('ark.404');
        }
        else{
            ark('event')->trigger('ark.dispatch');
            $this->dispatch($r);
        }
        ark('event')->trigger('ark.shutdown');
    }
    
    /**
     * Dispatch
     * @param array $r
     */
    protected function dispatch($r){//$controller, $action, $params){
        //extract params for named pattern
        if(isset($r['params'])){
            foreach($r['params'] as $k => $v){
                ark('request')->setAttribute($k, $v);
            }
        }
        //callback handler
        if(isset($r['handler'])){
            call_user_func_array($r['handler'], array($r['params']));
            return true;
        }

        if($r['controller'] === ''){
            $r['controller'] = 'default';
        }
        if($r['action'] === ''){
            $r['action'] = 'index';
        }

        $controllerFile = APP_DIR.'/source/controller/'.$r['controller'].'Controller.php';
        if(!file_exists($controllerFile)){
            ark('event')->trigger('ark.404');
        }
        else{
            require_once($controllerFile);
            $classname = basename($r['controller']).'Controller';
            $methodName = $r['action'].'Action';
            $o = new $classname;
            if(!method_exists($o, $methodName)){
                ark('event')->trigger('ark.404');
            }
            else{
                $response = call_user_func(array($o, $methodName));
                if ($response instanceof ArkResponse) {
                    $response->prepare()->send();
                }
                elseif(null !== $response){
                    echo $response;
                }
            }
        }
    }
}

/**
 * Console app
 */
class ArkAppConsole extends ArkApp
{
    /**
     * {@inheritdoc}
     */
    protected function init(){
    }
    
    /**
     * {@inheritdoc}
     */
    public function run(){
    }
}

/**
 * Get service
 * @param string $name Service name
 * @return mixed
 */
function ark($name = null){
    static $container;
    if(null === $container){
        $service_configs = ark_config('services', array());
        $container = new ArkContainer($service_configs);
        
        //register internal service
        $container->set('event', new ArkEvent());

        $container->register('request', array(
            'class' => 'ArkRequest'
        ));
    }
    
    //return container if service not specified
    if(null === $name){
        return $container;
    }
    
    return $container->get($name);
}


/**
 * Service container
 */
class ArkContainer
{
    /**
     * service list
     */
    protected $services = array(
    );
    
    protected $configs = array();
    
    public function __construct($configs = array()){
        $this->configs = $configs;
    }

    /**
     * Get service by name
     */
    public function get($name){
        if(!isset($this->services[$name])){
            $this->initService($name);
             if(!isset($this->services[$name])){
                throw new Exception(sprintf('Service "%s" does not exist or can not be started', $name));
             }
        }

        return $this->services[$name];
    }

    public function set($name, $value){
        $this->services[$name] = $value;
    }

    public function register($name, $value){
        $this->configs[$name] = $value;
    }

    protected function initService($name){
        if(isset($this->configs[$name])){
            $service_config = $this->configs[$name];
            if(is_callable($service_config)){
                $service = call_user_func($service_config);
            }
            elseif(is_array($service_config)){
                if(isset($service_config['class'])){
                    if(isset($service_config['method'])){
                        $service = call_user_func_array(
                            $service_config['class'].'::'.$service_config['method'], 
                            isset($service_config['params'])?$service_config['params']:array()
                        );
                    }
                    else{
                        if(isset($service_config['params'])){
                            $r = new ReflectionClass($service_config['class']);
                            $service = $r->newInstanceArgs($service_config['params']);
                        }
                        else{
                            $service = new $service_config['class'];
                        }
                    }
                }
            }

            //inject container
            if(isset($service)){
                $this->set($name, $service);
                //ready event of service
                if(isset($this->services['event'])){
                    $this->get('event')->trigger($name.'.ready');
                }
            }
        }
    }
}

class ArkEvent
{
    protected $eventList = array();

    public function bind($event, $callback){
        if(!isset($this->eventList[$event])){
            $this->eventList[$event] = array($callback);
        }
        else{
            $this->eventList[$event][] = $callback;
        }
    }

    public function unbind($event){
        if(isset($this->eventList[$event])){
            unset($this->eventList[$event]);
        }
    }

    public function trigger($event){
        $args = func_get_args();
        array_shift($args);
        if(isset($this->eventList[$event])){
            foreach($this->eventList[$event] as $callback){
                if(false === call_user_func_array($callback, $args)){
                    break;
                }
            }
        }
    }
}

/**
 * Universal Autoloader
 */
class ArkAutoload
{
    static private $namespaces = array(
    );

    static private $files = array();
    
    static private $dirs = array();
    
    static private $prefixes = array();

    static public function load($name){
        //file
        if(self::loadFile($name)){
            return true;
        }
        
        //prefix
        
        //namespace
        if(self::loadNamespace($name)){
            return true;
        }
        
        //file
        if(self::loadDir($name)){
            return true;
        }

        return false;
    }

    static public function registerNamespace($namespace, $path){
        self::$namespaces[$namespace] = $path;
    }
    
    static private function loadNamespace($name){
        foreach (self::$namespaces as $namespace => $path) {
            $prefix_length = strlen($namespace);
            if(substr($name, 0, $prefix_length + 1) === $namespace.'\\'){
                $file = $path.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, substr($name, $prefix_length)).'.php';
                require($file);
                return true;
            }
        }
        return false;
    }

    static public function registerNamespaceOnce($namespace, $path){
        if(!isset(self::$namespaces[$namespace])){
            self::$namespaces[$namespace] = $path;       
        }
    }

    static public function registerFile($class, $file){
        self::$files[$class] = $file;
    }
    
    static public function loadFile($name){
        if(isset(self::$files[$name])){
            require(self::$files[$name]);
            return true;
        }
        return false;
    }
    
    static public function registerDir($dir, $hasChild = true){
        self::$dirs[$dir] = $hasChild;
    }
    
    static public function loadDir($name){
        $name_path = str_replace('_', '/', $name);
        foreach(self::$dirs as $dir => $hasChild){
            if($hasChild){
                $file = $dir.'/'.$name_path.'.php';
            }
            else{
                $file = $dir.'/'.$name.'.php';
            }
            if(file_exists($file)){
                require($file);
                return true;
            }
        }
        
        return false;
    }
}

#Shortcut functions


/**
 * 404 page
 */
function ark_404(){
    header("HTTP/1.0 404 Not Found");
    $file = APP_DIR.'/404.php';
    if(file_exists($file)){
        require($file);
    }
    
    exit;
}

function ark_parse_query_path(){
    static $q;
    if(null === $q){
        $q = array();
        $script_name = $_SERVER['SCRIPT_NAME'];
        $script_name_length = strlen($script_name);

        $request_uri = $_SERVER['REQUEST_URI'];

        $slash_pos = strrpos($script_name, '/');
        $base = substr($script_name, 0, $slash_pos);
        $q['base'] = $base;

        //is script name in uri?
        if(substr($request_uri, 0, $script_name_length) == $script_name){
            if(
                !isset($request_uri[$script_name_length]) 
                || 
                in_array($request_uri[$script_name_length], array('/', '?'))
            ){
                $request_basename = basename($script_name);
            }
        }
        else{
            $request_basename = null;
        }

        $urlinfo = parse_url($request_uri);
        
        if(null === $request_basename && !isset($_GET['r'])){
            $info = substr($urlinfo['path'], $slash_pos + 1);
        }
        else{
            $info = isset($_GET['r'])?$_GET['r']:'';
        }

        $q['path'] = $info;
    }
    return $q;
}


/**
 * Route
 * @param string $path
 * @param array $config
 * @return array|boolean
 */
function ark_route($path, $config = null){
    $params = array();
    if($config){
        foreach($config as $pattern => $target){
            $pattern = '#^'.$pattern.'$#';
            if(preg_match($pattern, $path, $match)){
                foreach($match as $k => $v){
                    if(is_string($k)){
                        $params[$k] = $v;
                    }
                }
                if(!is_string($target)){
                    return array(
                        'handler' => $target,
                        'params' => $params,
                    );
                }
                $path = preg_replace($pattern, $target, $path);
                break;
            }
        }
    }
    
    if(!preg_match('#^(?<c>(\w+/)*)(?<a>\w+)?$#', $path, $match)){
        return false;
    }
    return array(
        'controller' => rtrim($match['c'], '/'),
        'action' => $match['a'] === null?'':$match['a'],
        'params' => $params,
    );
}

/**
 * Add a route pattern with callback
 * @param string $pattern
 * @param callable $callback
 */
function ark_match($pattern, $callback){
    Ark::$configs['route'][$pattern] = $callback;
}

/**
 * Get config
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function ark_config($key, $default = null){
    return isset(Ark::$configs[$key])?Ark::$configs[$key]:$default;
}

/**
 * Generate url
 * @param string $path
 * @param mixed $params
 * @return string
 */
function ark_url($path = '', $params = null){
    $url = APP_URL;
    $rewrite = ark_config('rewrite', true);
    if($path !== ''){
        if($rewrite){
            $url.=$path;
        }
        else{
            $url.='?r='.$path;
        }
    }
    if(null !== $params){
        if(is_array($params)){
            $params = http_build_query($params);
        }
        if($rewrite){
            $url.='?'.$params;
        }
        else{
            $url.='&'.$params;
        }
    }
    
    return $url;
}

function ark_assets($path){
    return APP_URL.$path;
}

function ark_event($event, $callback){
    ark('event')->bind($event, $callback);
}

function ark_autoload_class($class, $file = null){
    if(is_array($class)){
        foreach($class as $k => $v){
            ArkAutoload::registerFile($k, $v);
        }
    }
    else{
        ArkAutoload::registerFile($class, $file);
    }
}

function ark_autoload_dir($dir, $hasChild = true){
    ArkAutoload::registerDir($dir, $hasChild);
}
