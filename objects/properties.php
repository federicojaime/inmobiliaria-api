<?php

namespace objects;

use services\FilterService;
use objects\Base;

class Properties extends Base
{
    private $table_name = "properties";
    private $table_amenities = "property_amenities";
    private $table_images = "property_images";

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function getProperties($params = [])
    {
        $filterService = new FilterService();
        $filters = $filterService->buildPropertyFilters($params);
        $orderBy = $filterService->buildOrderBy($params);

        $query = "SELECT p.*, pa.has_pool, pa.has_heating, pa.has_ac, pa.has_garden, pa.has_laundry, pa.has_parking, pa.has_central_heating, pa.has_lawn, pa.has_fireplace, pa.has_central_ac, pa.has_high_ceiling
                  FROM {$this->table_name} p
                  LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id";

        if (!empty($filters['conditions'])) {
            $query .= " WHERE " . implode(" AND ", $filters['conditions']);
        }

        $query .= " ORDER BY " . $orderBy;

        if (isset($params['page']) && isset($params['limit'])) {
            $page = max(1, intval($params['page']));
            $limit = max(1, min(50, intval($params['limit'])));
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT :limit OFFSET :offset";
            $filters['values']['limit'] = $limit;
            $filters['values']['offset'] = $offset;
        }

        parent::getAll($query, $filters['values']);
        return $this;
    }

    public function getPropertiesWithFilters($filters, $orderBy, $page = null, $limit = null)
    {
        $query = "SELECT p.*, pa.has_pool, pa.has_heating, pa.has_ac, pa.has_garden, pa.has_laundry, pa.has_parking, pa.has_central_heating, pa.has_lawn, pa.has_fireplace, pa.has_central_ac, pa.has_high_ceiling
                  FROM {$this->table_name} p
                  LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id";

        if (!empty($filters['conditions'])) {
            $query .= " WHERE " . implode(" AND ", $filters['conditions']);
        }

        $query .= " ORDER BY " . $orderBy;

        if ($page !== null && $limit !== null) {
            $page = max(1, intval($page));
            $limit = max(1, min(50, intval($limit)));
            $offset = ($page - 1) * $limit;
            $query .= " LIMIT :limit OFFSET :offset";
            $filters['values']['limit'] = $limit;
            $filters['values']['offset'] = $offset;
        }

        parent::getAll($query, $filters['values']);
        return $this;
    }

    public function getProperty($id)
    {
        // Se obtiene la propiedad junto con los campos de amenities y datos del propietario
        $query = "SELECT p.*, 
                  pa.has_pool, pa.has_heating, pa.has_ac, pa.has_garden, pa.has_laundry, 
                  pa.has_parking, pa.has_central_heating, pa.has_lawn, pa.has_fireplace, 
                  pa.has_central_ac, pa.has_high_ceiling,
                  o.id as owner_id, o.document_type, o.document_number, o.name as owner_name,
                  o.email as owner_email, o.phone as owner_phone, o.address as owner_address,
                  o.city as owner_city, o.province as owner_province, o.is_company
                  FROM {$this->table_name} p
                  LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id
                  LEFT JOIN owners o ON p.owner_id = o.id
                  WHERE p.id = :id";

        parent::getOne($query, ["id" => $id]);

        $result = parent::getResult();
        if ($result->ok && $result->data) {
            // Construir el objeto amenities
            $amenities = [
                "has_pool" => $result->data->has_pool,
                "has_heating" => $result->data->has_heating,
                "has_ac" => $result->data->has_ac,
                "has_garden" => $result->data->has_garden,
                "has_laundry" => $result->data->has_laundry,
                "has_parking" => $result->data->has_parking,
                "has_central_heating" => $result->data->has_central_heating,
                "has_lawn" => $result->data->has_lawn,
                "has_fireplace" => $result->data->has_fireplace,
                "has_central_ac" => $result->data->has_central_ac,
                "has_high_ceiling" => $result->data->has_high_ceiling,
            ];
            $result->data->amenities = $amenities;

            // Construir el objeto owner si existe
            if ($result->data->owner_id) {
                $result->data->owner = [
                    "id" => $result->data->owner_id,
                    "document_type" => $result->data->document_type,
                    "document_number" => $result->data->document_number,
                    "name" => $result->data->owner_name,
                    "email" => $result->data->owner_email,
                    "phone" => $result->data->owner_phone,
                    "address" => $result->data->owner_address,
                    "city" => $result->data->owner_city,
                    "province" => $result->data->owner_province,
                    "is_company" => $result->data->is_company
                ];
            }

            // Limpiamos los campos que ya no necesitamos
            unset(
                $result->data->has_pool,
                $result->data->has_heating,
                $result->data->has_ac,
                $result->data->has_garden,
                $result->data->has_laundry,
                $result->data->has_parking,
                $result->data->has_central_heating,
                $result->data->has_lawn,
                $result->data->has_fireplace,
                $result->data->has_central_ac,
                $result->data->has_high_ceiling,
                $result->data->document_type,
                $result->data->document_number,
                $result->data->owner_name,
                $result->data->owner_email,
                $result->data->owner_phone,
                $result->data->owner_address,
                $result->data->owner_city,
                $result->data->owner_province,
                $result->data->is_company
            );

            // Obtener imágenes asociadas
            $query = "SELECT * FROM {$this->table_images} WHERE property_id = :property_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["property_id" => $id]);
            $images = $stmt->fetchAll(\PDO::FETCH_OBJ);
            foreach ($images as $img) {
                $img->url = $img->image_url;
            }
            $result->data->images = $images;
        }
        return $this;
    }

    public function addProperty($data)
    {
        try {
            $this->conn->beginTransaction();

            if (empty($data['price_ars']) && empty($data['price_usd'])) {
                throw new \Exception("Se requiere al menos un precio (ARS o USD)");
            }

            if (empty($data['owner_id'])) {
                throw new \Exception("El propietario es requerido");
            }

            // Verificar que el propietario existe
            $query = "SELECT id FROM owners WHERE id = :owner_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["owner_id" => $data['owner_id']]);
            if (!$stmt->fetch()) {
                throw new \Exception("El propietario seleccionado no existe");
            }

            // Normalizar campos booleanos
            $booleanFields = ['garage', 'has_electricity', 'has_natural_gas', 'has_sewage', 'has_paved_street', 'featured', 'is_available'];
            foreach ($booleanFields as $field) {
                $data[$field] = isset($data[$field]) && ($data[$field] === true || $data[$field] === 1 || $data[$field] === '1' || $data[$field] === 'true') ? 1 : 0;
            }

            $query = "INSERT INTO {$this->table_name} SET
                title = :title,
                description = :description,
                type = :type,
                status = :status,
                price_ars = :price_ars,
                price_usd = :price_usd,
                covered_area = :covered_area,
                total_area = :total_area,
                bedrooms = :bedrooms,
                bathrooms = :bathrooms,
                garage = :garage,
                has_electricity = :has_electricity,
                has_natural_gas = :has_natural_gas,
                has_sewage = :has_sewage,
                has_paved_street = :has_paved_street,
                address = :address,
                city = :city,
                province = :province,
                featured = :featured,
                owner_id = :owner_id,
                latitude = :latitude,
                longitude = :longitude,
                is_available = :is_available,
                user_id = :user_id";

            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([
                "title" => $data['title'],
                "description" => $data['description'],
                "type" => $data['type'],
                "status" => $data['status'],
                "price_ars" => $data['price_ars'] ?? null,
                "price_usd" => $data['price_usd'] ?? null,
                "covered_area" => $data['covered_area'],
                "total_area" => $data['total_area'],
                "bedrooms" => $data['bedrooms'] ?? null,
                "bathrooms" => $data['bathrooms'] ?? null,
                "garage" => $data['garage'],
                "has_electricity" => $data['has_electricity'],
                "has_natural_gas" => $data['has_natural_gas'],
                "has_sewage" => $data['has_sewage'],
                "has_paved_street" => $data['has_paved_street'],
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'],
                "featured" => $data['featured'],
                "owner_id" => $data['owner_id'],
                "latitude" => isset($data['latitude']) ? floatval($data['latitude']) : null,
                "longitude" => isset($data['longitude']) ? floatval($data['longitude']) : null,
                "is_available" => isset($data['is_available']) ? $data['is_available'] : 1,
                "user_id" => isset($data['user_id']) ? intval($data['user_id']) : null
            ]);
            if ($result) {
                $property_id = $this->conn->lastInsertId();

                // Insertar amenities
                if (isset($data['amenities'])) {
                    $amenities = $data['amenities'];

                    // Normalizar campos booleanos de amenities
                    $amenityFields = [
                        'has_pool',
                        'has_heating',
                        'has_ac',
                        'has_garden',
                        'has_laundry',
                        'has_parking',
                        'has_central_heating',
                        'has_lawn',
                        'has_fireplace',
                        'has_central_ac',
                        'has_high_ceiling'
                    ];

                    $amenityValues = [];
                    foreach ($amenityFields as $field) {
                        $amenityValues[$field] = isset($amenities[$field]) &&
                            ($amenities[$field] === true ||
                                $amenities[$field] === 1 ||
                                $amenities[$field] === '1' ||
                                $amenities[$field] === 'true') ? 1 : 0;
                    }

                    $query = "INSERT INTO {$this->table_amenities} SET
                         property_id = :property_id,
                         has_pool = :has_pool,
                         has_heating = :has_heating,
                         has_ac = :has_ac,
                         has_garden = :has_garden,
                         has_laundry = :has_laundry,
                         has_parking = :has_parking,
                         has_central_heating = :has_central_heating,
                         has_lawn = :has_lawn,
                         has_fireplace = :has_fireplace,
                         has_central_ac = :has_central_ac,
                         has_high_ceiling = :has_high_ceiling";

                    $stmt = $this->conn->prepare($query);
                    $stmt->execute(array_merge(
                        ["property_id" => $property_id],
                        $amenityValues
                    ));
                }

                $this->conn->commit();

                $this->result = new \stdClass();
                $this->result->ok = true;
                $this->result->msg = "Propiedad creada exitosamente";
                $this->result->data = ['newId' => $property_id];
            } else {
                throw new \Exception("Error al crear la propiedad");
            }
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
        }

        return $this;
    }

    public function updateProperty($id, $data)
    {
        try {
            $this->conn->beginTransaction();

            /* Las validaciones y el inicio se mantienen igual */

            // Normalizar campos booleanos
            $booleanFields = ['garage', 'has_electricity', 'has_natural_gas', 'has_sewage', 'has_paved_street', 'featured', 'is_available'];
            foreach ($booleanFields as $field) {
                if (isset($data[$field])) {
                    $data[$field] = ($data[$field] === true || $data[$field] === 1 || $data[$field] === '1' || $data[$field] === 'true') ? 1 : 0;
                }
            }

            $query = "UPDATE {$this->table_name} SET
                title = :title,
                description = :description,
                type = :type,
                status = :status,
                price_ars = :price_ars,
                price_usd = :price_usd,
                covered_area = :covered_area,
                total_area = :total_area,
                bedrooms = :bedrooms,
                bathrooms = :bathrooms,
                garage = :garage,
                has_electricity = :has_electricity,
                has_natural_gas = :has_natural_gas,
                has_sewage = :has_sewage,
                has_paved_street = :has_paved_street,
                address = :address,
                city = :city,
                province = :province,
                featured = :featured";

            // Agregar campo is_available si se proporciona
            if (isset($data['is_available'])) {
                $query .= ", is_available = :is_available";
            }

            // Agregar campos de latitud y longitud si se proporcionan
            if (isset($data['latitude']) || isset($data['longitude'])) {
                $query .= ", latitude = :latitude, longitude = :longitude";
            }

            // Agregar la actualización del owner_id y user_id solo si se proporcionan
            if (!empty($data['owner_id'])) {
                $query .= ", owner_id = :owner_id";
            }

            if (!empty($data['user_id'])) {
                $query .= ", user_id = :user_id";
            }

            $query .= " WHERE id = :id";

            $updateData = [
                "id" => $id,
                "title" => $data['title'],
                "description" => $data['description'],
                "type" => $data['type'],
                "status" => $data['status'],
                "price_ars" => $data['price_ars'] ?? null,
                "price_usd" => $data['price_usd'] ?? null,
                "covered_area" => $data['covered_area'],
                "total_area" => $data['total_area'],
                "bedrooms" => $data['bedrooms'] ?? null,
                "bathrooms" => $data['bathrooms'] ?? null,
                "garage" => isset($data['garage']) ? $data['garage'] : 0,
                "has_electricity" => isset($data['has_electricity']) ? $data['has_electricity'] : 0,
                "has_natural_gas" => isset($data['has_natural_gas']) ? $data['has_natural_gas'] : 0,
                "has_sewage" => isset($data['has_sewage']) ? $data['has_sewage'] : 0,
                "has_paved_street" => isset($data['has_paved_street']) ? $data['has_paved_street'] : 0,
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'] ?? null,
                "featured" => isset($data['featured']) ? $data['featured'] : 0
            ];

            // Agregar is_available a los datos solo si se proporciona
            if (isset($data['is_available'])) {
                $updateData["is_available"] = $data['is_available'];
            }

            // Agregar latitud y longitud a los datos solo si se proporcionan
            if (isset($data['latitude']) || isset($data['longitude'])) {
                $updateData["latitude"] = isset($data['latitude']) ? floatval($data['latitude']) : null;
                $updateData["longitude"] = isset($data['longitude']) ? floatval($data['longitude']) : null;
            }

            // Agregar owner_id a los datos solo si se proporciona
            if (!empty($data['owner_id'])) {
                $updateData["owner_id"] = $data['owner_id'];
            }

            // Agregar user_id a los datos solo si se proporciona
            if (!empty($data['user_id'])) {
                $updateData["user_id"] = intval($data['user_id']);
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($updateData);
            // Verificar si se proporcionaron amenities
            if (isset($data['amenities'])) {
                $amenities = is_string($data['amenities']) ? json_decode($data['amenities'], true) : $data['amenities'];

                // Verificar si la propiedad ya tiene amenities
                $checkQuery = "SELECT property_id FROM {$this->table_amenities} WHERE property_id = :property_id";
                $checkStmt = $this->conn->prepare($checkQuery);
                $checkStmt->execute(["property_id" => $id]);
                $hasAmenities = $checkStmt->rowCount() > 0;

                // Normalizar campos booleanos de amenities
                $amenityFields = [
                    'has_pool',
                    'has_heating',
                    'has_ac',
                    'has_garden',
                    'has_laundry',
                    'has_parking',
                    'has_central_heating',
                    'has_lawn',
                    'has_fireplace',
                    'has_central_ac',
                    'has_high_ceiling'
                ];

                $amenityValues = [];
                foreach ($amenityFields as $field) {
                    $amenityValues[$field] = isset($amenities[$field]) &&
                        ($amenities[$field] === true ||
                            $amenities[$field] === 1 ||
                            $amenities[$field] === '1' ||
                            $amenities[$field] === 'true') ? 1 : 0;
                }

                if ($hasAmenities) {
                    // UPDATE si ya existen los amenities
                    $query = "UPDATE {$this->table_amenities} SET
                             has_pool = :has_pool,
                             has_heating = :has_heating,
                             has_ac = :has_ac,
                             has_garden = :has_garden,
                             has_laundry = :has_laundry,
                             has_parking = :has_parking,
                             has_central_heating = :has_central_heating,
                             has_lawn = :has_lawn,
                             has_fireplace = :has_fireplace,
                             has_central_ac = :has_central_ac,
                             has_high_ceiling = :has_high_ceiling
                             WHERE property_id = :property_id";
                } else {
                    // INSERT si no existen
                    $query = "INSERT INTO {$this->table_amenities} SET
                             property_id = :property_id,
                             has_pool = :has_pool,
                             has_heating = :has_heating,
                             has_ac = :has_ac,
                             has_garden = :has_garden,
                             has_laundry = :has_laundry,
                             has_parking = :has_parking,
                             has_central_heating = :has_central_heating,
                             has_lawn = :has_lawn,
                             has_fireplace = :has_fireplace,
                             has_central_ac = :has_central_ac,
                             has_high_ceiling = :has_high_ceiling";
                }

                $stmt = $this->conn->prepare($query);
                $stmt->execute(array_merge(
                    ["property_id" => $id],
                    $amenityValues
                ));
            }

            // Actualizar imágenes si se proporcionaron
            if (isset($data['images']) && !empty($data['images'])) {
                $this->updatePropertyImages($id, $data['images']);
            }

            $this->conn->commit();

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = "Propiedad actualizada exitosamente";
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
        }

        return $this;
    }
    public function getAvailableProperties($params = [])
    {
        // Asegurarse de que solo se devuelvan propiedades disponibles
        $params['is_available'] = 1;

        return $this->getProperties($params);
    }

    public function changeAvailabilityStatus($id, $isAvailable)
    {
        try {
            $query = "UPDATE {$this->table_name} 
                 SET is_available = :is_available 
                 WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                "id" => $id,
                "is_available" => $isAvailable ? 1 : 0
            ]);

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = $isAvailable
                ? "Propiedad marcada como disponible exitosamente"
                : "Propiedad marcada como no disponible exitosamente";

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    public function updatePropertyStatus($id, $status)
    {
        try {
            // Verificar que la propiedad existe
            $query = "SELECT id FROM {$this->table_name} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["id" => $id]);

            if (!$stmt->fetch()) {
                throw new \Exception("La propiedad no existe");
            }

            // Actualizar el estado de la propiedad
            $query = "UPDATE {$this->table_name} SET status = :status WHERE id = :id";

            parent::update($query, [
                "id" => $id,
                "status" => $status
            ]);

            if (parent::getResult()->ok) {
                $this->result = new \stdClass();
                $this->result->ok = true;
                $this->result->msg = "Estado de la propiedad actualizado exitosamente";
            }

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    // Método para obtener propiedades no disponibles (alquiladas o vendidas)
    public function getUnavailableProperties($params = [])
    {
        // Asegurarse de que solo se devuelvan propiedades no disponibles
        $params['is_available'] = 0;

        // Si se especifica un estado (alquilado o vendido), filtramos por él
        if (isset($params['status']) && in_array($params['status'], ['alquilado', 'vendido'])) {
            // No modificamos el parámetro status que ya viene establecido
        }

        return $this->getProperties($params);
    }

    // Método para cambiar el estado de la propiedad (alquilado/vendido) y marcarla como no disponible
    public function changePropertyStatus($id, $status)
    {
        try {
            // Validar que el estado sea válido
            if (!in_array($status, ['alquilado', 'vendido', 'disponible'])) {
                throw new \Exception("Estado no válido. Debe ser 'alquilado', 'vendido' o 'disponible'");
            }

            $isAvailable = ($status === 'disponible') ? 1 : 0;

            $query = "UPDATE {$this->table_name} 
                 SET status = :status, is_available = :is_available 
                 WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                "id" => $id,
                "status" => $status,
                "is_available" => $isAvailable
            ]);

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = "Estado de propiedad actualizado a '{$status}'";

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }



    public function deleteProperty($id)
    {
        try {
            $this->conn->beginTransaction();

            // Primero eliminar registros relacionados
            $query = "DELETE FROM {$this->table_images} WHERE property_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["id" => $id]);

            $query = "DELETE FROM {$this->table_amenities} WHERE property_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["id" => $id]);
            // Finalmente eliminar la propiedad
            $query = "DELETE FROM {$this->table_name} WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["id" => $id]);

            $this->conn->commit();

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = "Propiedad eliminada exitosamente";
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
        }

        return $this;
    }

    public function searchProperties($params)
    {
        $filterService = new FilterService();
        $filters = $filterService->buildPropertyFilters($params);
        $orderBy = $filterService->buildOrderBy($params);

        $page = isset($params['page']) ? intval($params['page']) : null;
        $limit = isset($params['limit']) ? intval($params['limit']) : null;

        return $this->getPropertiesWithFilters($filters, $orderBy, $page, $limit);
    }

    public function addPropertyImages($property_id, $images)
    {
        // Si no hay transacción activa, iniciar una; de lo contrario, usar la existente.
        $ownTransaction = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $ownTransaction = true;
            }

            if (empty($images)) {
                if ($ownTransaction) {
                    $this->conn->commit();
                }
                return $this;
            }

            // Insertar imágenes en lote para mayor eficiencia
            $placeholders = [];
            $params = [];

            foreach ($images as $index => $image) {
                $placeholders[] = "(:property_id_{$index}, :image_url_{$index}, :is_main_{$index})";
                $params["property_id_{$index}"] = $property_id;
                $params["image_url_{$index}"] = $image['url'];
                $params["is_main_{$index}"] = isset($image['is_main']) && $image['is_main'] ? 1 : 0;
            }

            if (!empty($placeholders)) {
                $query = "INSERT INTO {$this->table_images} (property_id, image_url, is_main) VALUES " . implode(', ', $placeholders);
                $stmt = $this->conn->prepare($query);
                $stmt->execute($params);
            }

            if ($ownTransaction) {
                $this->conn->commit();
            }
            return $this;
        } catch (\Exception $e) {
            if ($ownTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function updatePropertyImages($property_id, $images)
    {
        // Usamos la transacción activa (si existe) o iniciamos la nuestra
        $ownTransaction = false;
        try {
            if (!$this->conn->inTransaction()) {
                $this->conn->beginTransaction();
                $ownTransaction = true;
            }

            // Eliminar todas las imágenes actuales de la propiedad
            $query = "DELETE FROM {$this->table_images} WHERE property_id = :property_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["property_id" => $property_id]);

            if (empty($images)) {
                if ($ownTransaction) {
                    $this->conn->commit();
                }
                return $this;
            }

            // Insertar todas las imágenes enviadas en el array (inserción en lote)
            $placeholders = [];
            $params = [];

            foreach ($images as $index => $image) {
                $placeholders[] = "(:property_id_{$index}, :image_url_{$index}, :is_main_{$index})";
                $params["property_id_{$index}"] = $property_id;
                $params["image_url_{$index}"] = $image['url'];
                $params["is_main_{$index}"] = isset($image['is_main']) && $image['is_main'] ? 1 : 0;
            }

            if (!empty($placeholders)) {
                $query = "INSERT INTO {$this->table_images} (property_id, image_url, is_main) VALUES " . implode(', ', $placeholders);
                $stmt = $this->conn->prepare($query);
                $stmt->execute($params);
            }

            if ($ownTransaction) {
                $this->conn->commit();
            }
            return $this;
        } catch (\Exception $e) {
            if ($ownTransaction && $this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function deletePropertyImage($property_id, $image_id)
    {
        try {
            $query = "DELETE FROM {$this->table_images} 
                     WHERE id = :image_id AND property_id = :property_id";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                "image_id" => $image_id,
                "property_id" => $property_id
            ]);

            // Verificar si la imagen eliminada era la principal y si hay otras imágenes
            $checkQuery = "SELECT COUNT(*) as count, SUM(is_main) as has_main FROM {$this->table_images} WHERE property_id = :property_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute(["property_id" => $property_id]);
            $result = $checkStmt->fetch(\PDO::FETCH_OBJ);

            // Si no hay imagen principal pero sí hay imágenes, establecer la primera como principal
            if ($result->count > 0 && $result->has_main == 0) {
                $updateQuery = "UPDATE {$this->table_images} SET is_main = 1 
                               WHERE property_id = :property_id ORDER BY id LIMIT 1";
                $updateStmt = $this->conn->prepare($updateQuery);
                $updateStmt->execute(["property_id" => $property_id]);
            }

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = "Imagen eliminada exitosamente";

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    public function setMainImage($property_id, $image_id)
    {
        try {
            $this->conn->beginTransaction();

            // Verificar que la imagen existe y pertenece a la propiedad
            $checkQuery = "SELECT id FROM {$this->table_images} WHERE id = :image_id AND property_id = :property_id";
            $checkStmt = $this->conn->prepare($checkQuery);
            $checkStmt->execute([
                "image_id" => $image_id,
                "property_id" => $property_id
            ]);

            if ($checkStmt->rowCount() == 0) {
                throw new \Exception("La imagen no existe o no pertenece a esta propiedad");
            }

            // Quitar la marca de principal de todas las imágenes
            $query = "UPDATE {$this->table_images} 
                     SET is_main = 0 
                     WHERE property_id = :property_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["property_id" => $property_id]);

            // Marcar la imagen indicada como principal
            $query = "UPDATE {$this->table_images} 
                     SET is_main = 1 
                     WHERE id = :image_id AND property_id = :property_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                "image_id" => $image_id,
                "property_id" => $property_id
            ]);

            $this->conn->commit();

            $this->result = new \stdClass();
            $this->result->ok = true;
            $this->result->msg = "Imagen principal actualizada exitosamente";

            return $this;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }

            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();

            return $this;
        }
    }
}
