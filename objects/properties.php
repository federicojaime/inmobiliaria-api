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
        // Se obtiene la propiedad junto con los campos de amenities
        $query = "SELECT p.*, 
                  pa.has_pool, pa.has_heating, pa.has_ac, pa.has_garden, pa.has_laundry, pa.has_parking, pa.has_central_heating, pa.has_lawn, pa.has_fireplace, pa.has_central_ac, pa.has_high_ceiling 
                  FROM {$this->table_name} p
                  LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id
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
            // Eliminar duplicados de la raíz
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
                $result->data->has_high_ceiling
            );

            // Obtener imágenes asociadas y mapear para que cada objeto tenga "url" igual a "image_url"
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

            $query = "INSERT INTO {$this->table_name} SET
                     title = :title,
                     description = :description,
                     type = :type,
                     status = :status,
                     price_ars = :price_ars,
                     price_usd = :price_usd,
                     area_size = :area_size,
                     bedrooms = :bedrooms,
                     bathrooms = :bathrooms,
                     garage = :garage,
                     address = :address,
                     city = :city,
                     province = :province,
                     featured = :featured,
                     user_id = :user_id";

            parent::add($query, [
                "title" => $data['title'],
                "description" => $data['description'],
                "type" => $data['type'],
                "status" => $data['status'],
                "price_ars" => $data['price_ars'] ?? null,
                "price_usd" => $data['price_usd'] ?? null,
                "area_size" => $data['area_size'],
                "bedrooms" => $data['bedrooms'],
                "bathrooms" => $data['bathrooms'],
                "garage" => $data['garage'],
                "address" => $data['address'],
                "city" => $data['city'],
                "province" => $data['province'],
                "featured" => $data['featured'] ?? false,
                "user_id" => $data['user_id'] ?? null
            ]);

            $result = parent::getResult();
            if ($result->ok) {
                $property_id = $result->data['newId'];

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

                parent::add($query, [
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

                if (!empty($data['images'])) {
                    $this->addPropertyImages($property_id, $data['images']);
                }
            }

            $this->conn->commit();
            return $this;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function updateProperty($id, $data)
    {
        try {
            $this->conn->beginTransaction();

            $requiredFields = ['title', 'description', 'type', 'status', 'area_size'];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    throw new \Exception("El campo $field es requerido");
                }
            }

            $query = "UPDATE {$this->table_name} SET
                     title = :title,
                     description = :description,
                     type = :type,
                     status = :status,
                     price_ars = :price_ars,
                     price_usd = :price_usd,
                     area_size = :area_size,
                     bedrooms = :bedrooms,
                     bathrooms = :bathrooms,
                     garage = :garage,
                     address = :address,
                     city = :city,
                     province = :province,
                     featured = :featured
                     WHERE id = :id";

            $updateData = [
                "id" => $id,
                "title" => $data['title'],
                "description" => $data['description'],
                "type" => $data['type'],
                "status" => $data['status'],
                "price_ars" => $data['price_ars'] ?? null,
                "price_usd" => $data['price_usd'] ?? null,
                "area_size" => $data['area_size'],
                "bedrooms" => $data['bedrooms'] ?? null,
                "bathrooms" => $data['bathrooms'] ?? null,
                "garage" => isset($data['garage']) ? $data['garage'] : 0,
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'] ?? null,
                "featured" => isset($data['featured']) ? $data['featured'] : 0
            ];

            parent::update($query, $updateData);

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

                parent::update($query, [
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

            // Actualizar imágenes si se envía en $data un array 'images'
            if (isset($data['images'])) {
                $this->updatePropertyImages($id, $data['images']);
            }

            $this->conn->commit();
            return $this;
        } catch (\Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
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
