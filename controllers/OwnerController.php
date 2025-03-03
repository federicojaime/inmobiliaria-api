<?php

namespace controllers;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \objects\Owners;
use \utils\Validate;

class OwnerController
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getOwners(Request $request, Response $response)
    {
        $owners = new Owners($this->container->get("db"));
        $resp = $owners->getOwners()->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getOwnerByDocument(Request $request, Response $response, array $args)
    {
        $owners = new Owners($this->container->get("db"));
        $resp = $owners->getOwnerByDocument($args["type"], $args["number"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getOwner(Request $request, Response $response, array $args)
    {
        $owners = new Owners($this->container->get("db"));
        $resp = $owners->getOwner($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function createOwner(Request $request, Response $response)
    {
        $data = $request->getParsedBody();

        $verificar = [
            "document_type" => [
                "type" => "string"
            ],
            "document_number" => [
                "type" => "string",
                "min" => 5
            ],
            "name" => [
                "type" => "string",
                "min" => 3
            ]
        ];

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($data, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            // Asegurarse de que los campos booleanos se manejen correctamente
            if (isset($data['is_company'])) {
                $data['is_company'] = filter_var($data['is_company'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            $owners = new Owners($this->container->get("db"));
            $resp = $owners->addOwner($data)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function updateOwner(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();

        $verificar = [
            "document_type" => [
                "type" => "string"
            ],
            "document_number" => [
                "type" => "string",
                "min" => 5
            ],
            "name" => [
                "type" => "string",
                "min" => 3
            ]
        ];

        $validacion = new Validate($this->container->get("db"));
        $validacion->validar($data, $verificar);

        if ($validacion->hasErrors()) {
            $resp = $validacion->getErrors();
        } else {
            // Asegurarse de que los campos booleanos se manejen correctamente
            if (isset($data['is_company'])) {
                $data['is_company'] = filter_var($data['is_company'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }

            $owners = new Owners($this->container->get("db"));
            $resp = $owners->updateOwner($args["id"], $data)->getResult();
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }
    public function searchOwners(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $searchTerm = isset($queryParams['q']) ? $queryParams['q'] : '';

        if (empty($searchTerm)) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Término de búsqueda requerido";
            $resp->data = null;

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        $owners = new Owners($this->container->get("db"));
        $resp = $owners->searchOwners($searchTerm)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deleteOwner(Request $request, Response $response, array $args)
    {
        $owners = new Owners($this->container->get("db"));
        $resp = $owners->deleteOwner($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }
}
