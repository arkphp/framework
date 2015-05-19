<?php
/**
 * Shortcuts
 */

use Ark\Framework\BaseApp;
function app() {
    return BaseApp::$instance;
}

function service($name) {
    return app()[$name];
}

function config($key) {
    return app()->configs[$key];
}