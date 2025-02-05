<?php

namespace objects;

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

    public function getProperties($filters = [])
    {
        $conditions = [];
        $values = [];

        if (!empty($filters['type'])) {
            $conditions[] = "type = :type";
            $values['type'] = $filters['type'];
        }

        if (!empty($filters['status'])) {
            $conditions[] = "status = :status";
            $values['status'] = $filters['status'];
        }

        // Filtros para precios en ARS
        if (!empty($filters['min_price_ars'])) {
            $conditions[] = "price_ars >= :min_price_ars";
            $values['min_price_ars'] = $filters['min_price_ars'];
        }

        if (!empty($filters['max_price_ars'])) {
            $conditions[] = "price_ars <= :max_price_ars";
            $values['max_price_ars'] = $filters['max_price_ars'];
        }

        // Filtros para precios en USD
        if (!empty($filters['min_price_usd'])) {
            $conditions[] = "price_usd >= :min_price_usd";
            $values['min_price_usd'] = $filters['min_price_usd'];
        }

        if (!empty($filters['max_price_usd'])) {
            $conditions[] = "price_usd <= :max_price_usd";
            $values['max_price_usd'] = $filters['max_price_usd'];
        }

        if (!empty($filters['city'])) {
            $conditions[] = "city = :city";
            $values['city'] = $filters['city'];
        }

        $query = "SELECT p.*, pa.*
                 FROM {$this->table_name} p
                 LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id";

        if (!empty($conditions)) {
            $query .= " WHERE " . implode(" AND ", $conditions);
        }

        $query .= " ORDER BY p.id";

        if (!empty($values)) {
            parent::getAll($query, $values);
        } else {
            parent::getAll($query);
        }
        return $this;
    }

    public function getProperty($id)
    {
        $query = "SELECT p.*, pa.* 
                 FROM {$this->table_name} p
                 LEFT JOIN {$this->table_amenities} pa ON p.id = pa.property_id
                 WHERE p.id = :id";
        parent::getOne($query, ["id" => $id]);

        // Get images if property exists
        $result = parent::getResult();
        if ($result->ok && $result->data) {
            $query = "SELECT * FROM {$this->table_images} WHERE property_id = :property_id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["property_id" => $id]);
            $result->data->images = $stmt->fetchAll(\PDO::FETCH_OBJ);
        }

        return $this;
    }

    public function addProperty($data)
    {
        try {
            $this->conn->beginTransaction();

            // Validar que al menos un precio esté presente
            if (empty($data['price_ars']) && empty($data['price_usd'])) {
                throw new \Exception("Se requiere al menos un precio (ARS o USD)");
            }

            // Insert main property data
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
                     featured = :featured";

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
                "featured" => $data['featured'] ?? false
            ]);

            $result = parent::getResult();
            if ($result->ok) {
                $property_id = $result->data['newId'];

                // Insert amenities
                $query = "INSERT INTO {$this->table_amenities} SET
                         property_id = :property_id,
                         has_pool = :has_pool,
                         has_heating = :has_heating,
                         has_ac = :has_ac,
                         has_garden = :has_garden,
                         has_laundry = :has_laundry,
                         has_parking = :has_parking";

                parent::add($query, [
                    "property_id" => $property_id,
                    "has_pool" => $data['amenities']['has_pool'] ?? false,
                    "has_heating" => $data['amenities']['has_heating'] ?? false,
                    "has_ac" => $data['amenities']['has_ac'] ?? false,
                    "has_garden" => $data['amenities']['has_garden'] ?? false,
                    "has_laundry" => $data['amenities']['has_laundry'] ?? false,
                    "has_parking" => $data['amenities']['has_parking'] ?? false
                ]);

                // Handle images if present
                if (!empty($data['images'])) {
                    foreach ($data['images'] as $image) {
                        $query = "INSERT INTO {$this->table_images} SET
                                 property_id = :property_id,
                                 image_url = :image_url,
                                 is_main = :is_main";

                        parent::add($query, [
                            "property_id" => $property_id,
                            "image_url" => $image['url'],
                            "is_main" => $image['is_main'] ?? false
                        ]);
                    }
                }
            }

            $this->conn->commit();
            return $this;
        } catch (\Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateProperty($id, $data)
    {
        try {
            $this->conn->beginTransaction();

            // Validar que al menos un precio esté presente
            if (empty($data['price_ars']) && empty($data['price_usd'])) {
                throw new \Exception("Se requiere al menos un precio (ARS o USD)");
            }

            // Update main property data
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
                "bedrooms" => $data['bedrooms'],
                "bathrooms" => $data['bathrooms'],
                "garage" => $data['garage'],
                "address" => $data['address'],
                "city" => $data['city'],
                "province" => $data['province'],
                "featured" => $data['featured'] ?? false
            ];

            parent::update($query, $updateData);

            // Update amenities
            $query = "UPDATE {$this->table_amenities} SET
                     has_pool = :has_pool,
                     has_heating = :has_heating,
                     has_ac = :has_ac,
                     has_garden = :has_garden,
                     has_laundry = :has_laundry,
                     has_parking = :has_parking
                     WHERE property_id = :property_id";

            parent::update($query, [
                "property_id" => $id,
                "has_pool" => $data['amenities']['has_pool'] ?? false,
                "has_heating" => $data['amenities']['has_heating'] ?? false,
                "has_ac" => $data['amenities']['has_ac'] ?? false,
                "has_garden" => $data['amenities']['has_garden'] ?? false,
                "has_laundry" => $data['amenities']['has_laundry'] ?? false,
                "has_parking" => $data['amenities']['has_parking'] ?? false
            ]);

            $this->conn->commit();
            return $this;
        } catch (\Exception $e) {
            $this->conn->rollBack();
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

        // Búsqueda por precio en ARS
        if (!empty($params['min_price_ars'])) {
            $conditions[] = "p.price_ars >= :min_price_ars";
            $values['min_price_ars'] = $params['min_price_ars'];
        }

        if (!empty($params['max_price_ars'])) {
            $conditions[] = "p.price_ars <= :max_price_ars";
            $values['max_price_ars'] = $params['max_price_ars'];
        }

        // Búsqueda por precio en USD
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
}
