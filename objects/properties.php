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
            owner_id = :owner_id";

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
                "garage" => $data['garage'] ? 1 : 0,
                "has_electricity" => isset($data['has_electricity']) ? 1 : 0,
                "has_natural_gas" => isset($data['has_natural_gas']) ? 1 : 0,
                "has_sewage" => isset($data['has_sewage']) ? 1 : 0,
                "has_paved_street" => isset($data['has_paved_street']) ? 1 : 0,
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'],
                "featured" => $data['featured'] ? 1 : 0,
                "owner_id" => $data['owner_id']
            ]);

            if ($result) {
                $property_id = $this->conn->lastInsertId();

                // Insertar amenities
                if (isset($data['amenities'])) {
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
                    $stmt->execute([
                        "property_id" => $property_id,
                        "has_pool" => $data['amenities']['has_pool'] ?? false,
                        "has_heating" => $data['amenities']['has_heating'] ?? false,
                        "has_ac" => $data['amenities']['has_ac'] ?? false,
                        "has_garden" => $data['amenities']['has_garden'] ?? false,
                        "has_laundry" => $data['amenities']['has_laundry'] ?? false,
                        "has_parking" => $data['amenities']['has_parking'] ?? false,
                        "has_central_heating" => $data['amenities']['has_central_heating'] ?? false,
                        "has_lawn" => $data['amenities']['has_lawn'] ?? false,
                        "has_fireplace" => $data['amenities']['has_fireplace'] ?? false,
                        "has_central_ac" => $data['amenities']['has_central_ac'] ?? false,
                        "has_high_ceiling" => $data['amenities']['has_high_ceiling'] ?? false
                    ]);
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

            $requiredFields = ['title', 'description', 'type', 'status', 'covered_area', 'total_area'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new \Exception("El campo $field es requerido");
                }
            }

            // Si se está actualizando el propietario, verificar que existe
            if (!empty($data['owner_id'])) {
                $query = "SELECT id FROM owners WHERE id = :owner_id";
                $stmt = $this->conn->prepare($query);
                $stmt->execute(["owner_id" => $data['owner_id']]);
                if (!$stmt->fetch()) {
                    throw new \Exception("El propietario seleccionado no existe");
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

            // Agregar la actualización del owner_id solo si se proporciona
            if (!empty($data['owner_id'])) {
                $query .= ", owner_id = :owner_id";
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
                "garage" => isset($data['garage']) && $data['garage'] == 1 ? 1 : 0,
                "has_electricity" => isset($data['has_electricity']) && $data['has_electricity'] == 1 ? 1 : 0,
                "has_natural_gas" => isset($data['has_natural_gas']) && $data['has_natural_gas'] == 1 ? 1 : 0,
                "has_sewage" => isset($data['has_sewage']) && $data['has_sewage'] == 1 ? 1 : 0,
                "has_paved_street" => isset($data['has_paved_street']) && $data['has_paved_street'] == 1 ? 1 : 0,
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'] ?? null,
                "featured" => isset($data['featured']) ? $data['featured'] : 0
            ];

            // Agregar owner_id a los datos solo si se proporciona
            if (!empty($data['owner_id'])) {
                $updateData["owner_id"] = $data['owner_id'];
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($updateData);

            // Actualizar amenities si se proporcionaron
            if (isset($data['amenities'])) {
                $amenities = is_string($data['amenities']) ? json_decode($data['amenities'], true) : $data['amenities'];

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

                $stmt = $this->conn->prepare($query);
                $stmt->execute([
                    "property_id" => $id,
                    "has_pool" => $amenities['has_pool'] ?? false,
                    "has_heating" => $amenities['has_heating'] ?? false,
                    "has_ac" => $amenities['has_ac'] ?? false,
                    "has_garden" => $amenities['has_garden'] ?? false,
                    "has_laundry" => $amenities['has_laundry'] ?? false,
                    "has_parking" => $amenities['has_parking'] ?? false,
                    "has_central_heating" => $amenities['has_central_heating'] ?? false,
                    "has_lawn" => $amenities['has_lawn'] ?? false,
                    "has_fireplace" => $amenities['has_fireplace'] ?? false,
                    "has_central_ac" => $amenities['has_central_ac'] ?? false,
                    "has_high_ceiling" => $amenities['has_high_ceiling'] ?? false
                ]);
            }

            // Actualizar imágenes si se proporcionaron
            if (isset($data['images'])) {
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
    public function deleteProperty($id)
    {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        parent::delete($query, ["id" => $id]);
        return $this;
    }

    public function searchProperties($params)
    {
        $conditions = [];
        $values = [];

        if (!empty($params['type'])) {
            $conditions[] = "p.type = :type";
            $values['type'] = $params['type'];
        }

        if (!empty($params['status'])) {
            $conditions[] = "p.status = :status";
            $values['status'] = $params['status'];
        }

        if (!empty($params['min_price_ars'])) {
            $conditions[] = "p.price_ars >= :min_price_ars";
            $values['min_price_ars'] = $params['min_price_ars'];
        }

        if (!empty($params['max_price_ars'])) {
            $conditions[] = "p.price_ars <= :max_price_ars";
            $values['max_price_ars'] = $params['max_price_ars'];
        }

        if (!empty($params['min_price_usd'])) {
            $conditions[] = "p.price_usd >= :min_price_usd";
            $values['min_price_usd'] = $params['min_price_usd'];
        }

        if (!empty($params['max_price_usd'])) {
            $conditions[] = "p.price_usd <= :max_price_usd";
            $values['max_price_usd'] = $params['max_price_usd'];
        }

        $query = "SELECT p.*, pa.* 
                 FROM {$this->table_name} p
                 LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id";

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        parent::getAll($query, $values);
        return $this;
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

            foreach ($images as $image) {
                $query = "INSERT INTO {$this->table_images} SET
                         property_id = :property_id,
                         image_url = :image_url,
                         is_main = :is_main";

                parent::add($query, [
                    "property_id" => $property_id,
                    "image_url" => $image['url'],
                    "is_main" => $image['is_main'] ? 1 : 0
                ]);

                if (!parent::getResult()->ok) {
                    throw new \Exception("Error al guardar la imagen");
                }
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
            parent::delete($query, ["property_id" => $property_id]);

            // Insertar todas las imágenes enviadas en el array
            foreach ($images as $image) {
                $query = "INSERT INTO {$this->table_images} SET
                         property_id = :property_id,
                         image_url = :image_url,
                         is_main = :is_main";
                parent::add($query, [
                    "property_id" => $property_id,
                    "image_url" => $image['url'],
                    "is_main" => $image['is_main'] ? 1 : 0
                ]);
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
        $query = "DELETE FROM {$this->table_images} 
                 WHERE id = :image_id AND property_id = :property_id";

        return parent::delete($query, [
            "image_id" => $image_id,
            "property_id" => $property_id
        ]);
    }

    public function setMainImage($property_id, $image_id)
    {
        try {
            $this->conn->beginTransaction();

            // Quitar la marca de principal de todas las imágenes
            $query = "UPDATE {$this->table_images} 
                     SET is_main = 0 
                     WHERE property_id = :property_id";
            parent::update($query, ["property_id" => $property_id]);

            // Marcar la imagen indicada como principal
            $query = "UPDATE {$this->table_images} 
                     SET is_main = 1 
                     WHERE id = :image_id AND property_id = :property_id";
            parent::update($query, [
                "image_id" => $image_id,
                "property_id" => $property_id
            ]);

            $this->conn->commit();
            return $this;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }
}
