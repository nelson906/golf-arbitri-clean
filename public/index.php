<?php
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Subdirectory fix: facciamo credere a Symfony che lo script sia a /arbitrigolf/index.php
$_SERVER['SCRIPT_NAME'] = '/arbitrigolf/index.php';
$_SERVER['PHP_SELF'] = '/arbitrigolf/index.php';

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../vendor/autoload.php';

(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());