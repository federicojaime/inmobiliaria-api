<?php

namespace controllers;

use \Psr\Http\Message\ResponseInterface as Response;
use \Psr\Http\Message\ServerRequestInterface as Request;
use \objects\Properties;
use \utils\PropertyValidator;
use \services\ImageService;

class PropertyController
{
    private $container;

    public function __construct($container)
    {
        $this->container = $container;
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

        // Construir datos para validación
        $data = [
            'title' => $params['title'] ?? null,
            'description' => $params['description'] ?? null,
            'type' => $params['type'] ?? null,
            'status' => $params['status'] ?? null,
            'price_ars' => isset($params['price_ars']) && $params['price_ars'] !== '' ? floatval($params['price_ars']) : null,
            'price_usd' => isset($params['price_usd']) && $params['price_usd'] !== '' ? floatval($params['price_usd']) : null,
            'covered_area' => isset($params['covered_area']) ? floatval($params['covered_area']) : null,
            'total_area' => isset($params['total_area']) ? floatval($params['total_area']) : null,
            'bedrooms' => isset($params['bedrooms']) ? intval($params['bedrooms']) : null,
            'bathrooms' => isset($params['bathrooms']) ? intval($params['bathrooms']) : null,
            'garage' => isset($params['garage']) ? filter_var($params['garage'], FILTER_VALIDATE_BOOLEAN) : false,
            'has_electricity' => isset($params['has_electricity']) ? filter_var($params['has_electricity'], FILTER_VALIDATE_BOOLEAN) : false,
            'has_natural_gas' => isset($params['has_natural_gas']) ? filter_var($params['has_natural_gas'], FILTER_VALIDATE_BOOLEAN) : false,
            'has_sewage' => isset($params['has_sewage']) ? filter_var($params['has_sewage'], FILTER_VALIDATE_BOOLEAN) : false,
            'has_paved_street' => isset($params['has_paved_street']) ? filter_var($params['has_paved_street'], FILTER_VALIDATE_BOOLEAN) : false,
            'address' => $params['address'] ?? null,
            'city' => $params['city'] ?? null,
            'province' => $params['province'] ?? null,
            'featured' => isset($params['featured']) ? filter_var($params['featured'], FILTER_VALIDATE_BOOLEAN) : false,
            'owner_id' => isset($params['owner_id']) ? intval($params['owner_id']) : null
        ];

        // Decodificar los amenities enviados como JSON
        $data['amenities'] = isset($params['amenities']) ? json_decode($params['amenities'], true) : [];

        // Validación de datos
        $validator = new PropertyValidator();
        if (!$validator->validate($data)) {
            $response->getBody()->write(json_encode($validator->getFormattedErrors()));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        try {
            $properties = new Properties($this->container->get('db'));
            $result = $properties->addProperty($data)->getResult();

            // Procesar imágenes si se subieron
            if ($result->ok && !empty($uploadedFiles['images'])) {
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
                        $isMain = isset($imagesMain[$index]) && $imagesMain[$index] == '1';
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

        // Si amenities viene como JSON, decodificarlo
        if (isset($fields['amenities'])) {
            $fields['amenities'] = json_decode($fields['amenities'], true);
        }

        // Procesar campos numéricos
        $fields['covered_area'] = isset($fields['covered_area']) ? floatval($fields['covered_area']) : null;
        $fields['total_area'] = isset($fields['total_area']) ? floatval($fields['total_area']) : null;
        $fields['price_ars'] = isset($fields['price_ars']) ? floatval($fields['price_ars']) : null;
        $fields['price_usd'] = isset($fields['price_usd']) ? floatval($fields['price_usd']) : null;
        $fields['bedrooms'] = isset($fields['bedrooms']) ? intval($fields['bedrooms']) : null;
        $fields['bathrooms'] = isset($fields['bathrooms']) ? intval($fields['bathrooms']) : null;

        // Procesar campos booleanos - Verificación estricta
        $fields['garage'] = isset($fields['garage']) && $fields['garage'] == 1;
        $fields['has_electricity'] = isset($fields['has_electricity']) && $fields['has_electricity'] == 1;
        $fields['has_natural_gas'] = isset($fields['has_natural_gas']) && $fields['has_natural_gas'] == 1;
        $fields['has_sewage'] = isset($fields['has_sewage']) && $fields['has_sewage'] == 1;
        $fields['has_paved_street'] = isset($fields['has_paved_street']) && $fields['has_paved_street'] == 1;
        $fields['featured'] = isset($fields['featured']) && $fields['featured'] == 1;

        // Procesar imágenes existentes (se envían como texto en el FormData)
        $images = [];
        if (isset($fields['existing_images'])) {
            $existingImages = is_array($fields['existing_images']) ? $fields['existing_images'] : [$fields['existing_images']];
            $existingImagesMain = isset($fields['existing_images_main'])
                ? (is_array($fields['existing_images_main']) ? $fields['existing_images_main'] : [$fields['existing_images_main']])
                : [];
            for ($i = 0; $i < count($existingImages); $i++) {
                $images[] = [
                    'url' => $existingImages[$i],
                    'is_main' => (isset($existingImagesMain[$i]) && $existingImagesMain[$i] == '1') ? true : false
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
                        'is_main' => (isset($newImagesMain[$index]) && $newImagesMain[$index] == '1') ? true : false
                    ];
                }
            }
        }

        // Si se procesaron imágenes, asignarlas al campo 'images'
        if (!empty($images)) {
            $fields['images'] = $images;
        }

        // Validación básica de campos requeridos
        $requiredFields = [
            'title',
            'description',
            'type',
            'status',
            'covered_area',
            'total_area',
            'province'
        ];
        $missing = [];
        foreach ($requiredFields as $field) {
            if (!isset($fields[$field]) || trim($fields[$field]) === '') {
                $missing[] = "El campo $field es requerido";
            }
        }

        if (!empty($missing)) {
            $resp = new \stdClass();
            $resp->ok = false;
            $resp->msg = "Hay campos requeridos sin completar";
            $resp->errores = $missing;
            $response->getBody()->write(json_encode($resp));
            return $response
                ->withHeader("Content-Type", "application/json")
                ->withStatus(400);
        }

        try {
            $properties = new Properties($this->container->get("db"));
            $resp = $properties->updateProperty($args["id"], $fields)->getResult();
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

        foreach ($uploadedFiles['images'] as $image) {
            $result = $imageService->uploadImage($image);
            if ($result['success']) {
                $uploadedImages[] = [
                    'url' => $result['path'],
                    'is_main' => false
                ];
            }
        }

        if (!empty($uploadedImages)) {
            $data = ['images' => $uploadedImages];
            $resp = $properties->updateProperty($args['id'], $data)->getResult();
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
}
