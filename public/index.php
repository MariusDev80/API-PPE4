<?php

use PPE4\Service\Database;
use PPE4\Service\JwtService;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;



require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

require_once __DIR__ . '/../src/Service/Database.php';
$db = new Database();

// Add Slim routing middleware
$app->addRoutingMiddleware();

$app->addBodyParsingMiddleware();

// Set the base path to run the app in a subdirectory.
// This path is used in urlFor().
$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);

$routes = require __DIR__ . '/../app/routes.php';
$routes($app, $db);

// Run app
$app->run();
