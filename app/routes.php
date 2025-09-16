<?php

use Firebase\JWT\Key;
use PPE4\Service\Database;
use Slim\App;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Firebase\JWT\JWT;

const ACCESS = [
    "administrateur" => [
        "administrateur",
        "badge",
        "categ_soins",
        "categorie_indisponibilite",
        "chambre_forte",
        "convalescence",
        "indisponibilite",
        "infirmiere",
        "infirmiere_badge",
        "lieu_convalescence",
        "patient",
        "personne",
        "personne_login",
        "soins",
        "soins_visite",
        "temoignage",
        "token",
        "type_soins",
        "visite"
    ], 
    "chef" => [
        "infirmiere",
        "convalescence",
        "patient",
        "soins",
        "soins_visite",
        "visite",
        "type_soins",
        "temoignage"
    ],
    "infirmiere" => [
        "visite",
        "personne"
    ],
    "patient" => [
        "visite",
        "temoignage"
    ]
];

function isActive($user) {
    return $user && $user['nb_tentative_erreur'] < 4;
}

function verifToken(Request $request) {
    
    $returns = [
        'payload' => null,
        'error' => null
    ];

    try {

        $authHeaders = $request->getHeader('Authorization');
        $token = null;

        if ($authHeaders == null || count($authHeaders) == 0) {
            $returns['error'] = "Unauthorized";
            return $returns;
        } 

        if (count($authHeaders) == 1) {
            $token = str_contains($request->getHeader('Authorization')[0], "Bearer") ? substr($authHeaders[0], 7) : $returns['error'] = "Header malformed";
        } else {
            foreach ($authHeaders as $header) {
                if (str_contains($header, "Bearer ")) {
                    $token = substr($authHeaders[0], 7);
                    break;
                }
                $returns['error'] = "Header malformed";
            }
        }

        if($token) {
            try {
                $returns['payload'] = (array)JWT::decode($token, new Key("API-KEY", "HS256"));
            } catch(Exception $e) {
                $returns['error'] = $e->getMessage();
            }
    
        } else {
            $returns['error'] = "Unauthorized";
        }

    } catch(Exception $e) {
        $returns['error'] = $e->getMessage();
    }

    return $returns;
}

function hasAccess($payload, $table) {
    $verif = false;
    if ($payload['fonction']) {
        $verif = in_array($table, ACCESS[$payload['fonction']]);
    }
    return $verif;
}

function canModify($payload, $table, $id, $db) {
    $data = $db->listHaving($table, "id", $id);

    if($data[0][$payload['fonction']] == $payload["loggedInAs"]) {
        return true;
    } else {
        return false;
    }
}

return function (App $app, Database $db) {
    $app->get('/', function (Request $request, Response $response, $args) {
        $response->getBody()->write('Hello, World!');
        return $response;
    });

    $app->get('/changeStatus/{id}', function(Request $request, Response $response, $args) use ($db) {
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $status = 200;
    
        if ($payload && $payload['fonction'] === 'administrateur' && $args['id'] != $payload['loggedInAs']) {
            try {
                $id = $args['id'];
    
                $user = $db->findBy("personne_login", ['id' => $id]);
    
                if ($user) {
                    $newAttempts = isActive($user) ? 4 : 0;
    
                    $db->edit('personne_login', $id, ['nb_tentative_erreur' => $newAttempts]);
    
                    $response->getBody()->write(json_encode([
                        'message' => 'Status updated successfully',
                        'newAttempts' => $newAttempts
                    ]));
                } else {
                    $response->getBody()->write(json_encode(['error' => 'User not found']));
                    $status = 404;
                }
    
                return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>" . $e->getMessage());
                $status = 500;
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            if ($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }
    
        return $response->withStatus($status);
    });
    
    $app->get('/{table}/all', function (Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $table = $args['table'];
        $status = 200;

        if($payload && hasAccess($payload, $table)) {
            try {

                if($table == "personne" && $payload['accessType'] == 'restricted') {
                    $data = $db->rawExecute(
                        "SELECT DISTINCT p.*,pt.personne_de_confiance FROM personne p INNER JOIN visite v ON p.id = v.patient INNER JOIN patient pt ON p.id = pt.id WHERE v.infirmiere = :infirmiere;",
                        ['infirmiere' => $payload['loggedInAs']]);
                    
                    if($data) {
                        foreach($data as $key => $value) {
                            if($data[$key]['personne_de_confiance'] == null) {
                                $data[$key]['personne_de_confiance'] = [];
                            } else {
                                $data[$key]['personne_de_confiance'] = $db->findBy("personne", ['id' => $value['personne_de_confiance']]);
                            }
                        }
                    } else {
                        $data = ["message" => "No data found"];
                    }
                } else {
                    if($payload['accessType'] == "restricted") {
                        $data = $db->listHaving($table, $payload['fonction'], $payload['loggedInAs']);
                    } else {
                        $data = $db->list($table);
                    }
                }

                $response->getBody()->write(json_encode($data ? $data : []));
    
                $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $response->withHeader('Content-Type', 'html/text');
                $status = 500;
            }
        } else {
            if($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }


        return $response->withStatus($status)->withStatus($status);
    });

    $app->get('/{table}/{id}', function (Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $table = $args['table'];
        $status = 200;

        if($payload && hasAccess($payload, $table)) {
            try {

                if($table == "personne" && $payload['accessType'] == 'restricted') {
                    $id = $args['id'];
                    $data = $db->rawExecute(
                        "SELECT DISTINCT p.*,pt.personne_de_confiance FROM personne p INNER JOIN visite v ON p.id = v.patient INNER JOIN patient pt ON p.id = pt.id WHERE v.infirmiere = :infirmiere AND p.id = :id;",
                        [
                            'infirmiere' => $payload['loggedInAs'],
                            'id' => $id]);
                    
                    if($data) {
                        foreach($data as $key => $value) {
                            if($data[$key]['personne_de_confiance'] == null) {
                                $data[$key]['personne_de_confiance'] = [];
                            } else {
                                $data[$key]['personne_de_confiance'] = $db->findBy("personne", ['id' => $value['personne_de_confiance']]);
                            }
                        }
                    } else {
                        $data = ["message" => "No data found"];
                    }
                } else {
                    if($payload['accessType'] == "restricted") {
                        $data = null;
                        $req = $db->listHaving($table, $payload['fonction'], $payload['loggedInAs']);
                        foreach($req as $key => $value) {
                            if($value['id'] == $args['id']) {
                                $data = $value;
                                break;
                            }
                        }
                    } else {
                        $data = $db->listHaving($table, "id", $args['id']);
                    }
                }

                $response->getBody()->write(json_encode($data ? $data : []));
                
    
                $response->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $status = 500;
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            if($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }


        return $response->withStatus($status);
    });
    
    $app->delete('/{table}/delete/{id}', function(Request $request, Response $response, $args) use ($db) {   
        
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $table = $args['table'];
        $status = 200;
        
        if($payload && hasAccess($payload, $table) && canModify($payload, $table, $args['id'], $db)) {
            try {
                $id = $args['id'];
                $db->delete($table, $id);
                $response->getBody()->write(json_encode(['message' => 'Deleted']));
                
                
                return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
                
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $status = 500;
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            if($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }
        
        return $response->withStatus($status);
    });
    
    $app->put('/{table}/update/{id}', function(Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $table = $args['table'];
        $status = 200;
        
        if($payload && hasAccess($payload, $table) && canModify($payload, $table, $args['id'], $db)) {
            try {
                $id = $args['id'];
                $data = (array)$request->getParsedBody();
                if($table == "personne_login") {
                    $data['mp'] = hash("MD5", $data['mp']);
                }
                $db->edit($table, $id, $data);
                $response->getBody()->write(json_encode(['message' => 'Updated', 'object' => $db->findBy($table, ['id' => $id])]));
                
                
                return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
                
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $status = 500;
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            if($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }

        return $response->withStatus($status);
    });

    $app->post('/{table}/add', function(Request $request, Response $response, $args) use ($db) {
        
        $verif = verifToken($request);
        $payload = (array)$verif['payload'];
        $table = $args['table'];
        $status = 200;
        
        if($payload && hasAccess($payload, $table)) {
            try {
                $data = (array)$request->getParsedBody();
                if($table == "personne_login") {
                    $data['mp'] = hash("MD5", $data['mp']);
                }
                $id = $db->add($table, $data);
                $response->getBody()->write(json_encode(['message' => 'Added', 'object' => $db->find($table, $id)]));
                
    
                return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    
            } catch (Exception $e) {
                $response->getBody()->write("<h2>500 Internal Server Error</h2><br>".$e->getMessage());
                $status = 500;
                $response->withHeader('Content-Type', 'html/text');
            }
        } else {
            if($payload) {
                $response->getBody()->write("<h2>403 Forbidden</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 403;
            } else {
                $response->getBody()->write("<h2>401 Unauthorized</h2><br>");
                $response->withHeader('Content-Type', 'html/text');
                $status = 401;
            }
        }

        return $response->withStatus($status);
    });

    $app->get('/login/{role}/{login}/{password}', function(Request $request, Response $response, $args) use ($db) {
        $status = 200;
        $fonction = "";
        
        try {
            $params = [
                'login' => $args['login'],
                'mp' => hash("MD5", $args['password'])
            ];
            $user = $db->findBy("personne_login", $params);

            if($user && isActive($user)) {
                if ($args["role"] == "infimiere") {
                    $rq = $db->find("infirmiere", $user["id"]);
                    if ($rq["chef"]) {
                        $fonction = "chef";
                    } else {
                        $fonction = $rq ? "infirmiere" : null;
                    }
                } else {
                    $fonction = $db->find($args["role"], $user["id"]) ? $args["role"] : null;
                }

                $payload = [
                    'iat' => time(),
                    'exp' => time() + 3600,
                    'loggedInAs' => $user['id'],
                    'fonction' => $fonction,
                    'accessType' => $fonction == "administrateur" || $fonction == "chef" ? "full" : "restricted",
                ];

                $jwt = JWT::encode($payload, "API-KEY", "HS256");
                $response->getBody()->write(json_encode(["token" => $jwt]));
            } else {
                $response->getBody()->write(json_encode(["error" => "Unauthorized"]));
                $status = 401;
            }


        } catch(Exception $e) {
            $response->getBody()->write($e->getMessage());
            $status = 500;
            $response->withHeader('Content-Type', 'application/json');
        }

        return $response->withStatus($status);
    });

    $app->get('/verifToken', function(Request $request, Response $response, $args) {
        $verif = verifToken($request);
        $response->getBody()->write(json_encode($verif['payload'] ? $verif['payload'] : $verif['error']));
        return $response->withHeader('Content-Type', 'application/json');
    });
};