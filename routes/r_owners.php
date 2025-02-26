<?php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use objects\Owners;
use utils\Validate;

// Get all owners
$app->get("/owners", function (Request $request, Response $response) {
    $owners = new Owners($this->get("db"));
    $resp = $owners->getOwners()->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get owner by document type and number
$app->get("/owner/document/{type}/{number}", function (Request $request, Response $response, array $args) {
    $owners = new Owners($this->get("db"));
    $resp = $owners->getOwnerByDocument($args["type"], $args["number"])->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get single owner
$app->get("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $owners = new Owners($this->get("db"));
    $resp = $owners->getOwner($args["id"])->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Create new owner
$app->post("/owner", function (Request $request, Response $response) {
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

    $validacion = new Validate($this->get("db"));
    $validacion->validar($data, $verificar);

    if ($validacion->hasErrors()) {
        $resp = $validacion->getErrors();
    } else {
        $owners = new Owners($this->get("db"));
        $resp = $owners->addOwner($data)->getResult();
    }

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Update owner
$app->put("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
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

    $validacion = new Validate($this->get("db"));
    $validacion->validar($data, $verificar);

    if ($validacion->hasErrors()) {
        $resp = $validacion->getErrors();
    } else {
        $owners = new Owners($this->get("db"));
        $resp = $owners->updateOwner($args["id"], $data)->getResult();
    }

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Delete owner
$app->delete("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $owners = new Owners($this->get("db"));
    $resp = $owners->deleteOwner($args["id"])->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});