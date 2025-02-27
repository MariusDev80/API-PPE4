<?php

use PPE4\Service\Database;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;

return function (App $app, Database $db) {
    $app->get('/', function (Request $request, Response $response, $args) {
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

    $app->get('/table/{table}', function (Request $request, Response $response, $args) use ($db) {
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

    $app->get('/login/{type}/{login}/{password}', function(Request $request, Response $response, $args) use ($db) {
        try {
            $params = [
                'login' => $args['login'],
                'mp' => hash("MD5", $args['password'])
            ];
            $user = $db->findBy("personne_login", $params);

            if($user) {
                $fonction = $db->find($args["type"], $user["id"]) ? $args["type"] : null;
            }

            $payload = [
                'exp' => time() + 3600,
                'loggedInAs' => $user['id'],
                'fonction' => $fonction
            ];

            $jwt = JWT::encode($payload, "API-KEY", "HS256");
            $response->getBody()->write($jwt);
        } catch(Exception $e) {
            $response->getBody()->write($e->getMessage());
            $response->withStatus(500);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });

    $app->delete('/delete/{table}/{id}', function(Request $request, Response $response, $args) use ($db) {
        try {
            $table = $args['table'];
            $id = $args['id'];
            $db->delete($table, $id);
            $response->getBody()->write(json_encode(['message' => 'Deleted']));
            $response->withStatus(200);

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $response->getBody()->write("<h2><strong>500 Internal Server Error</strong><h2>");
            $response->withStatus(500);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });

    $app->put('put/{table}/{id}', function(Request $request, Response $response, $args) use ($db) {
        try {
            $table = $args['table'];
            $id = $args['id'];
            $data = (array)$request->getParsedBody();
            var_dump($data);
            $db->edit($table, $id, $data);
            $response->getBody()->write(json_encode(['message' => 'Updated']));
            $response->withStatus(200);

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $response->getBody()->write("<h2><strong>500 Internal Server Error</strong><h2>");
            $response->withStatus(500);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });

    $app->post('/add/{table}', function(Request $request, Response $response, $args) use ($db) {
        try {
            $table = $args['table'];
            $data = (array)$request->getParsedBody();
            $db->add($table, $data);
            $response->getBody()->write(json_encode(['message' => 'Added']));
            $response->withStatus(200);

            return $response->withHeader('Content-Type', 'application/json');

        } catch (Exception $e) {
            $response->getBody()->write("<h2><strong>500 Internal Server Error</strong><h2>");
            $response->withStatus(500);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });
};