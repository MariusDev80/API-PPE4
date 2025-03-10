<?php

use Firebase\JWT\Key;
use PPE4\Service\Database;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;

function verifToken(Request $request) {
    $token = str_contains($request->getHeader('Authorization')[0], "Bearer") ? substr($request->getHeader('Authorization')[0], 7) : null;

    $returns = [
        'payload' => null,
        'error' => null
    ];

    if($token) {
        try {
            $returns['payload'] = (array)JWT::decode($token, new Key("API-KEY", "HS256"));
        } catch(Exception $e) {
            $returns['error'] = $e->getMessage();
        }

    } else {
        $returns['error'] = "Unauthorized";
    }

    return $returns;
}

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
        
        $verif = verifToken($request);

        if($verif['payload']) {
            try {
                $table = $args['table'];
                $data = $db->list($table);
                $response->getBody()->write(json_encode($data));
                $response->withStatus(200);
    
                $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $response->withStatus(500);
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            $response->getBody()->write($verif['error']);
            $response->withStatus(401);
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
                'iat' => time(),
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
        
        $verif = verifToken($request);

        if($verif['payload']) {
            try {
                $table = $args['table'];
                $id = $args['id'];
                $db->delete($table, $id);
                $response->getBody()->write(json_encode(['message' => 'Deleted']));
                $response->withStatus(200);
    
                return $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $response->withStatus(500);
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            $response->getBody()->write($verif['error']);
            $response->withStatus(401);
            $response->withHeader('Content-Type', 'html/text');
        }
        
        return $response;
    });

    $app->put('/update/{table}/{id}', function(Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);

        if($verif['payload']) {
            try {
                $table = $args['table'];
                $id = $args['id'];
                $data = (array)$request->getParsedBody();
                if($table == "personne_login") {
                    $data['mp'] = hash("MD5", $data['mp']);
                }
                $db->edit($table, $id, $data);
                $response->getBody()->write(json_encode(['message' => 'Updated', 'object' => $db->findBy($table, ['id' => $id])]));
                $response->withStatus(200);
    
                return $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $response->withStatus(500);
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            $response->getBody()->write($verif['error']);
            $response->withStatus(401);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });

    $app->post('/add/{table}', function(Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);

        if($verif['payload']) {
            try {
                $table = $args['table'];
                $data = (array)$request->getParsedBody();
                if($table == "personne_login") {
                    $data['mp'] = hash("MD5", $data['mp']);
                }
                $id = $db->add($table, $data);
                $response->getBody()->write(json_encode(['message' => 'Added', 'object' => $db->find($table, $id)]));
                $response->withStatus(200);
    
                return $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $response->withStatus(500);
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            $response->getBody()->write($verif['error']);
            $response->withStatus(401);
            $response->withHeader('Content-Type', 'html/text');
        }

        return $response;
    });

    $app->get('/verifToken', function(Request $request, Response $response, $args) {
        $verif = verifToken($request);
        $response->getBody()->write(json_encode($verif['payload'] ? $verif['payload'] : $verif['error']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};