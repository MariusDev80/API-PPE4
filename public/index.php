<?php

use PPE4\Service\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Selective\BasePath\BasePathMiddleware;
use Slim\Factory\AppFactory;

require_once __DIR__ . '/../src/Service/Database.php';
require_once __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$db = new Database();

// Add Slim routing middleware
$app->addRoutingMiddleware();

// Set the base path to run the app in a subdirectory.
// This path is used in urlFor().
$app->add(new BasePathMiddleware($app));

$app->addErrorMiddleware(true, true, true);

// Define app routes
$app->get('/', function (Request $request, Response $response) {
    $response->getBody()->write('Hello, World!');
    return $response;
});

$app->get('/hello', function (Request $request, Response $response) {
    $args = $request->getQueryParams();
    if($args) {
        $response->getBody()->write('Hello ' . $args['name']);
        return $response;
    } else {
        $response->getBody()->write('Hello World');
    }
    return $response;
});

$app->get('/{table}', function (Request $request, Response $response, $args) use ($db) {
    try {
        $table = $args['table'];
        $data = $db->list($table);
        $response->getBody()->write(json_encode($data));
        $response->withStatus(200);

        return $response->withHeader('Content-Type', 'application/json');

    } catch (Exception $e) {
        $response->getBody()->write("<h2><strong>500 Internal Server Error</strong><h2>");
        $response->withStatus(500);
        $response->withHeader('Content-Type', 'html/text');
    }

    return $response;
});

// Run app
$app->run();