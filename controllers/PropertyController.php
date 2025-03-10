<?php

namespace controllers;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \objects\Properties;
use \objects\PropertyTypes;
use \utils\PropertyValidator;
use \services\ImageService;
use \services\FilterService;

class PropertyController
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
    }

    private function validatePropertyData($data, $isUpdate = false)
    {
        $validator = new PropertyValidator();
        if (!$validator->validate($data, $isUpdate)) {
            return $validator->getFormattedErrors();
        }
        return null;
    }

    private function normalizeBoolean($value)
    {
        return ($value === true || $value === 1 || $value === '1' || $value === 'true') ? 1 : 0;
    }

    public function getProperties(Request $request, Response $response)
    {
        $properties = new Properties($this->container->get("db"));
        $params = $request->getQueryParams();
        $resp = $properties->getProperties($params)->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getProperty(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->getProperty($args["id"])->getResult();
        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function createProperty(Request $request, Response $response)
    {
        $params = $request->getParsedBody();
        $uploadedFiles = $request->getUploadedFiles();

        // Obtener el ID del usuario de la sesión actual (JWT)
        $userId = null;
        $jwtData = $request->getAttribute('jwt');
        if ($jwtData && isset($jwtData['data']['id'])) {
            $userId = $jwtData['data']['id'];
        }

        // Construir datos para validación
        $data = [
            'title' => $params['title'] ?? null,
            'description' => $params['description'] ?? null,
            'type' => $params['type'] ?? null,
            'type_id' => isset($params['type_id']) ? intval($params['type_id']) : null,
            'status' => $params['status'] ?? null,
            'price_ars' => isset($params['price_ars']) && $params['price_ars'] !== '' ? floatval($params['price_ars']) : null,
            'price_usd' => isset($params['price_usd']) && $params['price_usd'] !== '' ? floatval($params['price_usd']) : null,
            'covered_area' => isset($params['covered_area']) ? floatval($params['covered_area']) : null,
            'total_area' => isset($params['total_area']) ? floatval($params['total_area']) : null,
            'bedrooms' => isset($params['bedrooms']) ? intval($params['bedrooms']) : null,
            'bathrooms' => isset($params['bathrooms']) ? intval($params['bathrooms']) : null,
            'garage' => isset($params['garage']) ? $this->normalizeBoolean($params['garage']) : 0,
            'has_electricity' => isset($params['has_electricity']) ? $this->normalizeBoolean($params['has_electricity']) : 0,
            'has_natural_gas' => isset($params['has_natural_gas']) ? $this->normalizeBoolean($params['has_natural_gas']) : 0,
            'has_sewage' => isset($params['has_sewage']) ? $this->normalizeBoolean($params['has_sewage']) : 0,
            'has_paved_street' => isset($params['has_paved_street']) ? $this->normalizeBoolean($params['has_paved_street']) : 0,
            'address' => $params['address'] ?? null,
            'city' => $params['city'] ?? null,
            'province' => $params['province'] ?? null,
            'featured' => isset($params['featured']) ? $this->normalizeBoolean($params['featured']) : 0,
            'owner_id' => isset($params['owner_id']) ? intval($params['owner_id']) : null,
            'latitude' => isset($params['latitude']) && $params['latitude'] !== '' ? floatval($params['latitude']) : null,
            'longitude' => isset($params['longitude']) && $params['longitude'] !== '' ? floatval($params['longitude']) : null,
            'is_available' => isset($params['is_available']) ? $this->normalizeBoolean($params['is_available']) : 1,
            'user_id' => $userId
        ];

        // Si se proporciona type pero no type_id, intentar obtener el type_id correspondiente
        if (empty($data['type_id']) && !empty($data['type'])) {
            $propertyTypes = new PropertyTypes($this->container->get("db"));
            $result = $propertyTypes->getPropertyTypes()->getResult();

            if ($result->ok && !empty($result->data)) {
                foreach ($result->data as $type) {
                    if (strtolower($type->name) === strtolower($data['type'])) {
                        $data['type_id'] = $type->id;
                        break;
                    }
                }
            }
        }

        // Decodificar los amenities enviados como JSON
        if (isset($params['amenities'])) {
            $data['amenities'] = is_string($params['amenities']) ? json_decode($params['amenities'], true) : $params['amenities'];
        } else {
            $data['amenities'] = [];
        }

        // Validar datos
        $errors = $this->validatePropertyData($data);
        if ($errors) {
            $response->getBody()->write(json_encode($errors));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        try {
            $properties = new Properties($this->container->get('db'));
            $result = $properties->addProperty($data)->getResult();

            // Procesar imágenes si se subieron
            if ($result->ok && isset($result->data['newId']) && !empty($uploadedFiles['images'])) {
                $imageService = new ImageService();
                $uploadedImages = [];
                $files = $uploadedFiles['images'];
                $imagesMain = isset($params['images_main'])
                    ? (is_array($params['images_main']) ? $params['images_main'] : [$params['images_main']])
                    : [];

                // Si no es un array, forzarlo a array
                if (!is_array($files)) {
                    $files = [$files];
                }

                foreach ($files as $index => $file) {
                    $uploadResult = $imageService->uploadImage($file);
                    if ($uploadResult['success']) {
                        $isMain = isset($imagesMain[$index]) && $this->normalizeBoolean($imagesMain[$index]);
                        $uploadedImages[] = [
                            'url' => $uploadResult['path'],
                            'is_main' => $isMain
                        ];
                    }
                }

                if (!empty($uploadedImages)) {
                    $properties->addPropertyImages($result->data['newId'], $uploadedImages);
                }
            }

            $response->getBody()->write(json_encode($result));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus($result->ok ? 200 : 400);
        } catch (\Exception $e) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = $e->getMessage();
            $resp->errores = [$e->getMessage()];

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(500);
        }
    }

    public function updateProperty(Request $request, Response $response, array $args)
    {
        // Obtener campos de texto
        $fields = $request->getParsedBody();
        // Obtener archivos subidos
        $uploadedFiles = $request->getUploadedFiles();

        // Construir datos para validación
        $data = [
            'title' => $fields['title'] ?? null,
            'description' => $fields['description'] ?? null,
            'type' => $fields['type'] ?? null,
            'type_id' => isset($fields['type_id']) ? intval($fields['type_id']) : null,
            'status' => $fields['status'] ?? null,
            'price_ars' => isset($fields['price_ars']) && $fields['price_ars'] !== '' ? floatval($fields['price_ars']) : null,
            'price_usd' => isset($fields['price_usd']) && $fields['price_usd'] !== '' ? floatval($fields['price_usd']) : null,
            'covered_area' => isset($fields['covered_area']) ? floatval($fields['covered_area']) : null,
            'total_area' => isset($fields['total_area']) ? floatval($fields['total_area']) : null,
            'bedrooms' => isset($fields['bedrooms']) ? intval($fields['bedrooms']) : null,
            'bathrooms' => isset($fields['bathrooms']) ? intval($fields['bathrooms']) : null,
            'garage' => isset($fields['garage']) ? $this->normalizeBoolean($fields['garage']) : 0,
            'has_electricity' => isset($fields['has_electricity']) ? $this->normalizeBoolean($fields['has_electricity']) : 0,
            'has_natural_gas' => isset($fields['has_natural_gas']) ? $this->normalizeBoolean($fields['has_natural_gas']) : 0,
            'has_sewage' => isset($fields['has_sewage']) ? $this->normalizeBoolean($fields['has_sewage']) : 0,
            'has_paved_street' => isset($fields['has_paved_street']) ? $this->normalizeBoolean($fields['has_paved_street']) : 0,
            'address' => $fields['address'] ?? null,
            'city' => $fields['city'] ?? null,
            'province' => $fields['province'] ?? null,
            'featured' => isset($fields['featured']) ? $this->normalizeBoolean($fields['featured']) : 0,
            'owner_id' => isset($fields['owner_id']) ? intval($fields['owner_id']) : null,
            'latitude' => isset($fields['latitude']) && $fields['latitude'] !== '' ? floatval($fields['latitude']) : null,
            'longitude' => isset($fields['longitude']) && $fields['longitude'] !== '' ? floatval($fields['longitude']) : null
        ];

        // Si se proporciona type pero no type_id, intentar obtener el type_id correspondiente
        if (empty($data['type_id']) && !empty($data['type'])) {
            $propertyTypes = new PropertyTypes($this->container->get("db"));
            $result = $propertyTypes->getPropertyTypes()->getResult();

            if ($result->ok && !empty($result->data)) {
                foreach ($result->data as $type) {
                    if (strtolower($type->name) === strtolower($data['type'])) {
                        $data['type_id'] = $type->id;
                        break;
                    }
                }
            }
        }

        // Si amenities viene como JSON, decodificarlo
        if (isset($fields['amenities'])) {
            $data['amenities'] = is_string($fields['amenities']) ? json_decode($fields['amenities'], true) : $fields['amenities'];
        }

        // Validar datos
        $errors = $this->validatePropertyData($data, true);
        if ($errors) {
            $response->getBody()->write(json_encode($errors));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        // Procesar imágenes existentes y nuevas
        $images = [];

        // Procesar imágenes existentes
        if (isset($fields['existing_images'])) {
            $existingImages = is_array($fields['existing_images']) ? $fields['existing_images'] : [$fields['existing_images']];
            $existingImagesMain = isset($fields['existing_images_main'])
                ? (is_array($fields['existing_images_main']) ? $fields['existing_images_main'] : [$fields['existing_images_main']])
                : [];

            for ($i = 0; $i < count($existingImages); $i++) {
                $images[] = [
                    'url' => $existingImages[$i],
                    'is_main' => isset($existingImagesMain[$i]) && $this->normalizeBoolean($existingImagesMain[$i])
                ];
            }
        }

        // Procesar nuevas imágenes
        if (isset($uploadedFiles['images'])) {
            $newFiles = is_array($uploadedFiles['images']) ? $uploadedFiles['images'] : [$uploadedFiles['images']];
            $newImagesMain = isset($fields['images_main'])
                ? (is_array($fields['images_main']) ? $fields['images_main'] : [$fields['images_main']])
                : [];

            $imageService = new ImageService();
            foreach ($newFiles as $index => $file) {
                $uploadResult = $imageService->uploadImage($file);
                if ($uploadResult['success']) {
                    $images[] = [
                        'url' => $uploadResult['path'],
                        'is_main' => isset($newImagesMain[$index]) && $this->normalizeBoolean($newImagesMain[$index])
                    ];
                }
            }
        }

        // Si se procesaron imágenes, asignarlas al campo 'images'
        if (!empty($images)) {
            $data['images'] = $images;
        }

        try {
            $properties = new Properties($this->container->get("db"));
            $resp = $properties->updateProperty($args["id"], $data)->getResult();

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus($resp->ok ? 200 : 409);
        } catch (\Exception $e) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = $e->getMessage();
            $resp->errores = [$e->getMessage()];

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(500);
        }
    }

    public function getAvailableProperties(Request $request, Response $response)
    {
        $properties = new Properties($this->container->get("db"));
        $params = $request->getQueryParams();
        $resp = $properties->getAvailableProperties($params)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function changePropertyStatus(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $propertyId = $args['id'];

        // Validar que se proporcione un estado válido
        if (!isset($data['status']) || !in_array($data['status'], ['alquilado', 'vendido', 'disponible'])) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Debe proporcionar un estado válido: 'alquilado', 'vendido' o 'disponible'";

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        $properties = new Properties($this->container->get("db"));
        $resp = $properties->changePropertyStatus($propertyId, $data['status'])->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    // Método para obtener propiedades no disponibles (alquiladas/vendidas)
    public function getUnavailableProperties(Request $request, Response $response)
    {
        $properties = new Properties($this->container->get("db"));
        $params = $request->getQueryParams();
        $resp = $properties->getUnavailableProperties($params)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    // Método para cambiar directamente la disponibilidad (más simple que cambiar estado)
    public function changeAvailabilityStatus(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();
        $propertyId = $args['id'];

        // Validar que se proporcione un valor para is_available
        if (!isset($data['is_available'])) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Debe proporcionar el parámetro 'is_available'";

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        $isAvailable = $this->normalizeBoolean($data['is_available']);

        $properties = new Properties($this->container->get("db"));
        $resp = $properties->changeAvailabilityStatus($propertyId, $isAvailable)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function updatePropertyStatus(Request $request, Response $response, array $args)
    {
        $data = $request->getParsedBody();

        // Validar que se ha proporcionado el estado
        if (!isset($data['status']) || empty($data['status'])) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "El estado es requerido";
            $resp->errores = ["Debe proporcionar un estado válido"];

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        // Validar que el estado sea uno de los valores permitidos
        $allowedStatuses = ['sale', 'rent', 'temporary_rent', 'sold', 'rented', 'reserved'];
        if (!in_array($data['status'], $allowedStatuses)) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Estado no válido";
            $resp->errores = ["El estado debe ser uno de los siguientes: " . implode(", ", $allowedStatuses)];

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        try {
            $properties = new Properties($this->container->get("db"));
            $resp = $properties->updatePropertyStatus($args["id"], $data['status'])->getResult();

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus($resp->ok ? 200 : 409);
        } catch (\Exception $e) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = $e->getMessage();
            $resp->errores = [$e->getMessage()];

            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(500);
        }
    }

    public function getPropertiesByStatus(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));

        // Construir filtros con el estado proporcionado en la URL
        $filters = ['status' => $args['status']];

        // Fusionar con otros parámetros que pudieran venir en la consulta
        $queryParams = $request->getQueryParams();
        $filters = array_merge($filters, $queryParams);

        $resp = $properties->getProperties($filters)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deleteProperty(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->deleteProperty($args["id"])->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function searchProperties(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->searchProperties($queryParams)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getFeaturedProperties(Request $request, Response $response)
    {
        $properties = new Properties($this->container->get("db"));
        $filters = ['featured' => true];
        $resp = $properties->getProperties($filters)->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function uploadPropertyImages(Request $request, Response $response, array $args)
    {
        $imageService = new ImageService();
        $uploadedFiles = $request->getUploadedFiles();

        if (empty($uploadedFiles['images'])) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "No se han subido imágenes";
            $response->getBody()->write(json_encode($resp));
            return $response->withStatus(400);
        }

        $properties = new Properties($this->container->get("db"));
        $uploadedImages = [];
        $files = is_array($uploadedFiles['images']) ? $uploadedFiles['images'] : [$uploadedFiles['images']];
        $imagesMain = isset($request->getParsedBody()['images_main'])
            ? (is_array($request->getParsedBody()['images_main']) ? $request->getParsedBody()['images_main'] : [$request->getParsedBody()['images_main']])
            : [];

        foreach ($files as $index => $file) {
            $uploadResult = $imageService->uploadImage($file);
            if ($uploadResult['success']) {
                $isMain = isset($imagesMain[$index]) && $this->normalizeBoolean($imagesMain[$index]);
                $uploadedImages[] = [
                    'url' => $uploadResult['path'],
                    'is_main' => $isMain
                ];
            }
        }

        if (!empty($uploadedImages)) {
            try {
                $properties->addPropertyImages($args['id'], $uploadedImages);
                $resp = new \stdClass();
                $resp->ok = true;
                $resp->msg = "Imágenes subidas correctamente";
            } catch (\Exception $e) {
                $resp = new \stdClass();
                $resp->ok = false;
                $resp->msg = "Error al procesar las imágenes: " . $e->getMessage();
            }
        } else {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Error al procesar las imágenes";
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function setMainImage(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->setMainImage($args["id"], $args["image_id"])->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function deletePropertyImage(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->deletePropertyImage($args["id"], $args["image_id"])->getResult();

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    // Métodos para tipos de propiedades
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
    public function getPublicProperties(Request $request, Response $response)
    {
        $properties = new Properties($this->container->get("db"));
        $params = $request->getQueryParams();

        // Aseguramos que solo propiedades disponibles sean mostradas al público
        $params['is_available'] = 1;

        $resp = $properties->getProperties($params)->getResult();

        // Procesa imágenes y añade la URL de la imagen principal para cada propiedad
        if ($resp->ok && !empty($resp->data)) {
            foreach ($resp->data as $property) {
                // Obtener imágenes de la propiedad
                $query = "SELECT * FROM property_images WHERE property_id = :property_id";
                $stmt = $this->container->get("db")->prepare($query);
                $stmt->execute(["property_id" => $property->id]);
                $images = $stmt->fetchAll(\PDO::FETCH_OBJ);

                // Buscar la imagen principal
                $mainImage = null;
                foreach ($images as $image) {
                    if ($image->is_main) {
                        $mainImage = $image->image_url;
                        break;
                    }
                }

                // Si no hay imagen principal, usa la primera disponible
                if (!$mainImage && !empty($images)) {
                    $mainImage = $images[0]->image_url;
                }

                $property->main_image = $mainImage;
                $property->images = $images;
            }
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }

    public function getPublicProperty(Request $request, Response $response, array $args)
    {
        $properties = new Properties($this->container->get("db"));
        $resp = $properties->getProperty($args["id"])->getResult();

        // Verificar que la propiedad esté disponible para el público
        if ($resp->ok && $resp->data && isset($resp->data->is_available) && !$resp->data->is_available) {
            $resp->ok = false;
            $resp->msg = "La propiedad solicitada no está disponible";
            $resp->data = null;
        }

        $response->getBody()->write(json_encode($resp));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($resp->ok ? 200 : 409);
    }
}
