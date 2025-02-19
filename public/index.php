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

$app->get('/test', function (Request $request, Response $response, $args) {
    $args = $request->getQueryParams();
    if($args) {
        $response->getBody()->write($args['value']);
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

$app->get('/{table}/filter', function (Request $request, Response $response, $args) use ($db) {
    try {
        $queryParams = $request->getQueryParams();
        if($queryParams) {
            $columns = $db->getColumsName($args['table']);
            $params = manageArgs($columns, $queryParams);
            // $data = $db->filter($args['table'], $params);
            $response->getBody()->write(json_encode($params));
        } else {
            $table = $args['table'];
            $data = $db->list($table);
            $response->getBody()->write(json_encode($data));
        }
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


function manageArgs(array $columns, array $args)
{   
    $params = [
        'columns' => [],
        'defaultOperator' => 'AND',
        'where' => [],
        'defaultDirection' => 'ASC',
        'order' => [],
        'limit' => null,
    ];

    // key : columns | column name | order | limit
    foreach ($args as $key => $value) {
        switch($key) {
            // selected columns
            // ajouter la verification de l'existence des colonnes
            case 'columns':
                $col = explode(',', $value);
                foreach ($col as $c) {
                    if(in_array($c, $columns)) {
                        array_push($params['columns'],$c);
                    } else {
                        throw new Exception("Invalid column : " . $c);
                    }
                }
                break;

            // where clause with column and values
            case in_array($key, $columns):
                $conditions = explode(',', $value);
                foreach ($conditions as $condition) {
                    $values = explode(':', $condition);
                    array_push($params['where'],[
                        'column' => $values[0],
                        'operator' => sizeof($values) > 1 ? $values[1] : null,
                    ]);
                }
                break;
            
            // order by clause
            case 'order':
                $orders = explode(',', $value);
                foreach ($orders as $order) {
                    $values = explode(':', $order);
                    array_push($params['order'],[
                        'column' => $values[0],
                        'direction' => sizeof($values) > 1 ? $values[1] : null,
                    ]);
                }

                break;

            // limit clause
            case 'limit':
                $params['limit'] = $value;
                break;

            default:
                throw new Exception("Invalid argument : " . $key);
        }
    }

    return $params;
}