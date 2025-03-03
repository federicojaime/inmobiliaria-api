<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use controllers\UserController;
use controllers\OwnerController;
use controllers\PropertyController;

// Ruta principal
$app->get("/", function (Request $request, Response $response, array $args) {
    $data = [
        "ok" => true,
        "msg" => "Karttem Inmobiliaria API v1.0",
        "timestamp" => time()
    ];
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus(200);
});

// ======== RUTAS DE USUARIOS ========
// Obtener todos los usuarios
$app->get("/users", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->getUsers($request, $response);
});

// Obtener un usuario por ID
$app->get("/user/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->getUser($request, $response, $args);
});

// Login
$app->post("/user/login", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->login($request, $response);
});

// Crear un nuevo usuario
$app->post("/user", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->createUser($request, $response);
});

// Actualizar un usuario
$app->put("/user/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->updateUser($request, $response, $args);
});

// Eliminar un usuario
$app->delete("/user/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new UserController($this);
    return $controller->deleteUser($request, $response, $args);
});

// ======== RUTAS DE PROPIETARIOS ========
// Obtener todos los propietarios
$app->get("/owners", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->getOwners($request, $response);
});

// Obtener un propietario por ID
$app->get("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->getOwner($request, $response, $args);
});

// Obtener propietario por tipo y número de documento
$app->get("/owner/document/{type}/{number}", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->getOwnerByDocument($request, $response, $args);
});

// Crear un nuevo propietario
$app->post("/owner", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->createOwner($request, $response);
});

// Actualizar un propietario
$app->put("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->updateOwner($request, $response, $args);
});

// Eliminar un propietario
$app->delete("/owner/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new OwnerController($this);
    return $controller->deleteOwner($request, $response, $args);
});

// ======== RUTAS DE PROPIEDADES ========
// Obtener todas las propiedades
$app->get("/properties", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->getProperties($request, $response);
});

// Obtener propiedad por ID
$app->get("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->getProperty($request, $response, $args);
});

// Crear una nueva propiedad
$app->post("/property", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->createProperty($request, $response);
});

// Actualizar una propiedad (usando POST para multipart/form-data)
$app->post("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->updateProperty($request, $response, $args);
});

// Eliminar una propiedad
$app->delete("/property/{id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->deleteProperty($request, $response, $args);
});

// Buscar propiedades
$app->get("/properties/search", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    return $controller->searchProperties($request, $response);
});

// Obtener propiedades destacadas
$app->get("/properties/featured", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    return $controller->getFeaturedProperties($request, $response);
});
// Obtener propiedades por estado (alquiladas, en venta, etc)

$app->get("/properties/status/{status}", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    $params = $request->getQueryParams(); // Obtener otros parámetros de consulta
    $params['status'] = $args['status']; // Añadir el estado desde la URL
    return $controller->getPropertiesByStatus($request, $response, $args);
});

// Búsqueda de propietarios por texto
$app->get("/owners/search", function (Request $request, Response $response) {
    $controller = new OwnerController($this);
    return $controller->searchOwners($request, $response);
});

$app->get("/properties/available", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    return $controller->getAvailableProperties($request, $response);
});

// Subir imágenes para una propiedad
$app->post("/property/{id:[0-9]+}/images", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->uploadPropertyImages($request, $response, $args);
});

// Rutas para manejar cambios de estado de propiedades

// Ruta para marcar como alquilado/vendido/disponible
$app->patch("/properties/{id}/status", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->changePropertyStatus($request, $response, $args);
});

// Actualizar estado de una propiedad
$app->patch("/property/{id:[0-9]+}/status", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->updatePropertyStatus($request, $response, $args);
});

// Ruta para cambiar disponibilidad
$app->patch("/properties/{id}/availability", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->changeAvailabilityStatus($request, $response, $args);
});

// Ruta para obtener propiedades no disponibles (alquiladas/vendidas)
$app->get("/properties/unavailable", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    return $controller->getUnavailableProperties($request, $response);
});

// Ruta para obtener propiedades alquiladas
$app->get("/properties/rented", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    $request = $request->withQueryParams(array_merge(
        $request->getQueryParams(),
        ['status' => 'alquilado', 'is_available' => 0]
    ));
    return $controller->getProperties($request, $response);
});

// Ruta para obtener propiedades vendidas
$app->get("/properties/sold", function (Request $request, Response $response) {
    $controller = new PropertyController($this);
    $request = $request->withQueryParams(array_merge(
        $request->getQueryParams(),
        ['status' => 'vendido', 'is_available' => 0]
    ));
    return $controller->getProperties($request, $response);
});

// Establecer una imagen como principal
$app->put("/property/{id:[0-9]+}/image/{image_id:[0-9]+}/main", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->setMainImage($request, $response, $args);
});

// Eliminar una imagen de una propiedad
$app->delete("/property/{id:[0-9]+}/image/{image_id:[0-9]+}", function (Request $request, Response $response, array $args) {
    $controller = new PropertyController($this);
    return $controller->deletePropertyImage($request, $response, $args);
});
