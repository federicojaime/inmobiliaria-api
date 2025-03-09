<?php

namespace controllers;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \objects\PropertyTypes;
use \utils\Validate;

class PropertyTypeController
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getPropertyTypes(Request $request, Response $response)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->getPropertyTypes()->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getActivePropertyTypes(Request $request, Response $response)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->getActivePropertyTypes()->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getPropertyType(Request $request, Response $response, array $args)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->getPropertyType($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function createPropertyType(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        $verificar = [
            "name" => [
                "type" => "string",
                "min" => 2,
                "max" => 50
            ]
        ];

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($data, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            // Normalizar el campo active
            if (isset($data['active'])) {
                $data['active'] = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            $propertyTypes = new PropertyTypes($this->container->get("db"));
            $resp = $propertyTypes->addPropertyType($data)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function updatePropertyType(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();

        $verificar = [
            "name" => [
                "type" => "string",
                "min" => 2,
                "max" => 50
            ]
        ];

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($data, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            // Normalizar el campo active
            if (isset($data['active'])) {
                $data['active'] = filter_var($data['active'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            $propertyTypes = new PropertyTypes($this->container->get("db"));
            $resp = $propertyTypes->updatePropertyType($args["id"], $data)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deletePropertyType(Request $request, Response $response, array $args)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->deletePropertyType($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function activatePropertyType(Request $request, Response $response, array $args)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->activatePropertyType($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deactivatePropertyType(Request $request, Response $response, array $args)
    {
        $propertyTypes = new PropertyTypes($this->container->get("db"));
        $resp = $propertyTypes->deactivatePropertyType($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }
}