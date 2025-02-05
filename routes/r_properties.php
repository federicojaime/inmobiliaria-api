<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use objects\Properties;
use utils\Validate;

// Get all properties
$app->get("/properties", function (Request $request, Response $response) {
    $properties = new Properties($this->get("db"));
    $resp = $properties->getProperties()->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get single property
$app->get("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $properties = new Properties($this->get("db"));
    $resp = $properties->getProperty($args["id"])->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Create new property
$app->post("/property", function (Request $request, Response $response) {
    $uploadedFiles = $request->getUploadedFiles();
    $params = $request->getParsedBody();
    
    // Debug: Ver qué datos estamos recibiendo
    error_log('Datos recibidos: ' . print_r($params, true));
    error_log('Archivos recibidos: ' . print_r($uploadedFiles, true));

    // Construir el array de datos
    $data = [
        'title' => $params['title'] ?? null,
        'description' => $params['description'] ?? null,
        'type' => $params['type'] ?? null,
        'status' => $params['status'] ?? null,
        'price_ars' => isset($params['price_ars']) && $params['price_ars'] !== '' ? floatval($params['price_ars']) : null,
        'price_usd' => isset($params['price_usd']) && $params['price_usd'] !== '' ? floatval($params['price_usd']) : null,
        'area_size' => isset($params['area_size']) ? floatval($params['area_size']) : null,
        'bedrooms' => isset($params['bedrooms']) ? intval($params['bedrooms']) : null,
        'bathrooms' => isset($params['bathrooms']) ? intval($params['bathrooms']) : null,
        'garage' => isset($params['garage']) ? filter_var($params['garage'], FILTER_VALIDATE_BOOLEAN) : false,
        'address' => $params['address'] ?? null,
        'city' => $params['city'] ?? null,
        'province' => $params['province'] ?? null,
        'featured' => isset($params['featured']) ? filter_var($params['featured'], FILTER_VALIDATE_BOOLEAN) : false
    ];

    // Decodificar amenities si existe
    if (isset($params['amenities'])) {
        $data['amenities'] = json_decode($params['amenities'], true);
    }

    // Procesar imágenes
    if (!empty($uploadedFiles['images'])) {
        $data['images'] = $uploadedFiles['images'];
    }

    // Debug: Ver datos procesados
    error_log('Datos procesados: ' . print_r($data, true));

    // Validaciones básicas
    if (empty($data['title'])) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "El título es requerido";
        $resp->errores = ["El título es requerido"];
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    if (empty($data['type'])) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "El tipo de propiedad es requerido";
        $resp->errores = ["El tipo de propiedad es requerido"];
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    if (empty($data['status'])) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "El estado de la propiedad es requerido";
        $resp->errores = ["El estado de la propiedad es requerido"];
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    if (empty($data['price_ars']) && empty($data['price_usd'])) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "Se requiere al menos un precio (ARS o USD)";
        $resp->errores = ["Debe especificar al menos un precio en ARS o USD"];
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    try {
        $properties = new Properties($this->get('db'));
        $result = $properties->addProperty($data)->getResult();
        
        $response->getBody()->write(json_encode($result));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($result->ok ? 200 : 400);
    } catch (\Exception $e) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = $e->getMessage();
        $resp->errores = [$e->getMessage()];
        
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(500);
    }
});

// Update property
$app->put("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $fields = $request->getParsedBody();

    $verificar = [
        "title" => [
            "type" => "string",
            "min" => 3,
            "max" => 200
        ],
        "description" => [
            "type" => "string",
            "min" => 10
        ],
        "type" => [
            "type" => "string"
        ],
        "status" => [
            "type" => "string"
        ],
        "area_size" => [
            "type" => "number",
            "min" => 0
        ]
    ];

    $validacion = new Validate($this->get("db"));
    $validacion->validar($fields, $verificar);

    if ($validacion->hasErrors()) {
        $resp = $validacion->getErrors();
    } else {
        $properties = new Properties($this->get("db"));
        $resp = $properties->updateProperty($args["id"], $fields)->getResult();
    }

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Delete property
$app->delete("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $properties = new Properties($this->get("db"));
    $resp = $properties->deleteProperty($args["id"])->getResult();
    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Search properties
$app->get("/properties/search", function (Request $request, Response $response) {
    $queryParams = $request->getQueryParams();

    $properties = new Properties($this->get("db"));
    $resp = $properties->searchProperties($queryParams)->getResult();

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get featured properties
$app->get("/properties/featured", function (Request $request, Response $response) {
    $properties = new Properties($this->get("db"));
    $filters = ['featured' => true];
    $resp = $properties->getProperties($filters)->getResult();

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get properties by type (house, apartment, etc)
$app->get("/properties/type/{type}", function (Request $request, Response $response, array $args) {
    $properties = new Properties($this->get("db"));
    $filters = ['type' => $args['type']];
    $resp = $properties->getProperties($filters)->getResult();

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get properties by status (sale, rent, temporary_rent)
$app->get("/properties/status/{status}", function (Request $request, Response $response, array $args) {
    $properties = new Properties($this->get("db"));
    $filters = ['status' => $args['status']];
    $resp = $properties->getProperties($filters)->getResult();

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Get properties by city
$app->get("/properties/city/{city}", function (Request $request, Response $response, array $args) {
    $properties = new Properties($this->get("db"));
    $filters = ['city' => $args['city']];
    $resp = $properties->getProperties($filters)->getResult();

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});

// Upload property images
$app->post("/property/{id:[0-9]+}/images", function (Request $request, Response $response, array $args) {
    $uploadedFiles = $request->getUploadedFiles();

    if (empty($uploadedFiles['images'])) {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "No se han subido imágenes";
        $resp->data = null;

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus(400);
    }

    $properties = new Properties($this->get("db"));
    $imageData = [];

    foreach ($uploadedFiles['images'] as $image) {
        if ($image->getError() === UPLOAD_ERR_OK) {
            $extension = pathinfo($image->getClientFilename(), PATHINFO_EXTENSION);
            $basename = bin2hex(random_bytes(8));
            $filename = sprintf('%s.%0.8s', $basename, $extension);

            $image->moveTo("uploads/" . $filename);

            $imageData[] = [
                'url' => 'uploads/' . $filename,
                'is_main' => false
            ];
        }
    }

    if (!empty($imageData)) {
        $resp = $properties->addPropertyImages($args['id'], $imageData)->getResult();
    } else {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "Error al procesar las imágenes";
        $resp->data = null;
    }

    $response->getBody()->write(json_encode($resp));
    return $response
        ->withHeader("Content-Type", "application/json")
        ->withStatus($resp->ok ? 200 : 409);
});
