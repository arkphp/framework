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
        return Ark::getHttpErrorResponse(404);
    }

    public function handleExceptionDefault($exception)
    {
        $view = new ArkViewPHP();
        $http_code = 500;
        $message = ArkResponse::getStatusMessageByCode($http_code);
        if (ARK_APP_DEBUG) {
            $message .= '<br /><pre>'.$exception.'</pre>';
        }
        
        return new ArkResponse($view->render(ARK_PATH.'/internal/view/http_error.html.php', array(
            'code' => $http_code,
            'title' => ArkResponse::getStatusTextByCode($http_code),
            'message' => $message,
        ), true), $http_code);
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
    public function dispatch($event){
        if(false !== $rule = $this->router->match($event->data)){
            //action
            if(!isset($rule['handler']) || (is_string($rule['handler']) && !function_exists($rule['handler']))){
                $action = array();
                $handler = $rule['handler'];
                if($rule['handler'][0] === '@'){
                    $parts = explode('/', $rule['handler'], 2);
                    $action['_bundle'] = substr($parts[0], 1);
                    $handler = $parts[1];
                }
                else{
                    $handler = ltrim($handler, '/');
                }

                if($handler !== ''){
                    $last_slash = strrpos($handler, '/');
                    if(false === $last_slash){
                        $action['_action'] = $handler;
                    }
                    //ends with slash
                    elseif($last_slash === strlen($handler) - 1){
                        $action['_controller'] = substr($handler, 0, -1);
                    }
                    else{
                        $action['_controller'] = substr($handler, 0, $last_slash);
                        $action['_action'] = substr($handler, $last_slash + 1);
                    }
                }

                if(!isset($action['_bundle'])){
                    if(isset($rule['attributes']['_bundle'])){
                        $action['_bundle'] = $rule['attributes']['_bundle'];
                    }
                }
                if(!isset($action['_controller']) && $action['_controller'] !== ''){
                    if(isset($rule['attributes']['_controller'])){
                        $action['_controller'] = $rule['attributes']['_controller'];
                    }
                    // else{
                    //     $action['_controller'] = 'default';
                    // }
                }
                if(!isset($action['_action'])){
                    if(isset($rule['attributes']['_action'])){
                        $action['_action'] = $rule['attributes']['_action'];
                    }
                    else{
                        $action['_action'] = 'index';
                    }
                }

                if(false === $handler = $this->findAction($action)){
                    $this->dispatchResponseEvent('app.404', $this);
                    return;
                }
            }
            //callable handler
            else{
                $handler = $rule['handler'];
            }
            
            if(isset($rule['attributes'])){
                foreach($rule['attributes'] as $k => $v){
                    $this->request->setAttribute($k, $v);
                }
            }

            $handler_params = _ark_handler_params($handler, $rule['attributes']);
            return call_user_func_array($handler, $handler_params);
        }
        else{
            $this->dispatchResponseEvent('app.404', $this);
            return;
        }
    }
}