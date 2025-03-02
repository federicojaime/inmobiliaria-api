<?php

namespace controllers;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Firebase\JWT\JWT;
use \objects\Users;
use \utils\Validate;

class UserController
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getUsers(Request $request, Response $response)
    {
        $users = new Users($this->container->get("db"));
        $resp = $users->getUsers()->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getUser(Request $request, Response $response, array $args)
    {
        $users = new Users($this->container->get("db"));
        $resp = $users->getUser($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function createUser(Request $request, Response $response)
    {
        $fields = $request->getParsedBody();

        $verificar = [
            "email" => [
                "type" => "string",
                "isValidMail" => true,
                "unique" => "users"
            ],
            "firstname" => [
                "type" => "string",
                "min" => 3,
                "max" => 50
            ],
            "lastname" => [
                "type" => "string",
                "min" => 3,
                "max" => 50
            ],
            "password" => [
                "type" => "string",
                "min" => 3,
                "max" => 20
            ]
        ];

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($fields, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            $users = new Users($this->container->get("db"));
            $resp = $users->setUser($fields)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function updateUser(Request $request, Response $response, array $args)
    {
        $fields = $request->getParsedBody();
        $fields['id'] = $args['id'];

        $verificar = [
            "email" => [
                "type" => "string",
                "isValidMail" => true
            ],
            "firstname" => [
                "type" => "string",
                "min" => 3,
                "max" => 50
            ],
            "lastname" => [
                "type" => "string",
                "min" => 3,
                "max" => 50
            ]
        ];

        // Si se proporciona una nueva contraseña, verificarla
        if (!empty($fields['password'])) {
            $verificar["password"] = [
                "type" => "string",
                "min" => 3,
                "max" => 20
            ];
        }

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($fields, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            $users = new Users($this->container->get("db"));
            $resp = $users->updateUser($fields)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deleteUser(Request $request, Response $response, array $args)
    {
        $users = new Users($this->container->get("db"));
        $resp = $users->deleteUser($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function login(Request $request, Response $response)
    {
        $fields = $request->getParsedBody();

        $verificar = [
            "email" => [
                "type" => "string",
                "isValidMail" => true
            ],
            "password" => [
                "type" => "string",
                "min" => 3,
                "max" => 20
            ]
        ];

        $validacion = new Validate();
        $validacion->validar($fields, $verificar);

        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "Nombre de usuario o contraseña incorrecto.";
        $resp->data = null;

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            $users = new Users($this->container->get("db"));
            $existe = $users->userExist($fields["email"]);
            if ($existe && password_verify($fields["password"], $existe->password)) {
                $iss = "http://localhost/inmobiliaria-api";
                $aud = "http://localhost/inmobiliaria-api";
                $iat = time();
                $exp = $iat + (3600 * 8); // Expire (8Hs)
                $nbf = $iat;
                $token = array(
                    "iss" => $iss,
                    "aud" => $aud,
                    "iat" => $iat,
                    "exp" => $exp,
                    "nbf" => $nbf,
                    "data" => array(
                        "id" => $existe->id,
                        "firstname" => $existe->firstname,
                        "lastname" => $existe->lastname,
                        "email" => $existe->email
                    )
                );
                unset($existe->password);
                $jwt = JWT::encode($token, $_ENV["JWT_SECRET_KEY"], $_ENV["JWT_ALGORITHM"]);
                // Asegúrate de que el token se devuelve con el prefijo "Bearer"
                $existe->jwt = "Bearer " . $jwt;
                $resp->ok = true;
                $resp->msg = "Login exitoso";
                $resp->data = $existe;
            }
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 401);
    }
}
