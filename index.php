<?php declare(strict_types=1);

require_once __DIR__ . "/app/core/Log.php";
set_exception_handler(function(Throwable $exception) {

    Log::error("Uncaught exception:", $exception->getMessage());
    http_response_code(500);
    header("Content-Type: text/plain");
    echo "Internal Server Error";

    die();
});

// Main
require_once __DIR__ . "/app/core/Env.php";
require_once __DIR__ . "/app/core/Database.php";
// Handler
require_once __DIR__ . "/app/core/Repository.php";
require_once __DIR__ . "/app/core/Service.php";
require_once __DIR__ . "/app/core/Controller.php";
// HTTP
require_once __DIR__ . "/app/core/Request.php";
require_once __DIR__ . "/app/core/Response.php";
require_once __DIR__ . "/app/core/Router.php";

Env::load(__DIR__ . "/.env");

$router = new Router();
require_once __DIR__ . "/routes.php";
$router->run();
