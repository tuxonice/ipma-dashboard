<?php

declare(strict_types=1);

/**
 * Front controller.
 *
 * Every HTTP request is routed through this file. It bootstraps the
 * application kernel and asks it to handle the current request.
 */

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');

$debug = filter_var($_SERVER['APP_DEBUG'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$env = $_SERVER['APP_ENV'] ?? 'dev';
if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}

$kernel = new Kernel($env, $debug);

$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
