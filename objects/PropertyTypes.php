<?php

namespace objects;

use objects\Base;

class PropertyTypes extends Base
{
    private $table_name = "property_types";

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function getPropertyTypes()
    {
        $query = "SELECT * FROM {$this->table_name} ORDER BY name";
        parent::getAll($query);
        return $this;
    }

    public function getPropertyType($id)
    {
        $query = "SELECT * FROM {$this->table_name} WHERE id = :id";
        parent::getOne($query, ["id" => $id]);
        return $this;
    }

    public function addPropertyType($data)
    {
        try {
            // Validar que el nombre no esté vacío
            if (empty($data['name'])) {
                throw new \Exception("El nombre del tipo de propiedad es requerido");
            }

            $query = "INSERT INTO {$this->table_name} SET
                     name = :name,
                     description = :description,
                     active = :active";

            parent::add($query, [
                "name" => $data['name'],
                "description" => isset($data['description']) ? $data['description'] : null,
                "active" => isset($data['active']) ? ($data['active'] ? 1 : 0) : 1
            ]);

            // Asegurar que el resultado incluya el ID
            if (isset($this->result) && isset($this->result->data) && isset($this->result->data->newId)) {
                $this->result->data->id = $this->result->data->newId;
            }

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    public function updatePropertyType($id, $data)
    {
        try {
            // Validar que el nombre no esté vacío
            if (empty($data['name'])) {
                throw new \Exception("El nombre del tipo de propiedad es requerido");
            }

            $query = "UPDATE {$this->table_name} SET
                     name = :name,
                     description = :description,
                     active = :active
                     WHERE id = :id";

            parent::update($query, [
                "id" => $id,
                "name" => $data['name'],
                "description" => isset($data['description']) ? $data['description'] : null,
                "active" => isset($data['active']) ? ($data['active'] ? 1 : 0) : 1
            ]);

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    public function deletePropertyType($id)
    {
        try {
            // Primero verificar que el tipo de propiedad no esté en uso
            $query = "SELECT COUNT(*) as count FROM properties WHERE type_id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->execute(["id" => $id]);
            $result = $stmt->fetch(\PDO::FETCH_OBJ);
            
            if ($result->count > 0) {
                throw new \Exception("No se puede eliminar el tipo de propiedad porque está siendo utilizado por propiedades existentes");
            }

            $query = "DELETE FROM {$this->table_name} WHERE id = :id";
            parent::delete($query, ["id" => $id]);

            return $this;
        } catch (\Exception $e) {
            $this->result = new \stdClass();
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            return $this;
        }
    }

    // Método para desactivar un tipo de propiedad
    public function deactivatePropertyType($id)
    {
        $query = "UPDATE {$this->table_name} SET active = 0 WHERE id = :id";
        parent::update($query, ["id" => $id]);
        return $this;
    }

    // Método para activar un tipo de propiedad
    public function activatePropertyType($id)
    {
        $query = "UPDATE {$this->table_name} SET active = 1 WHERE id = :id";
        parent::update($query, ["id" => $id]);
        return $this;
    }

    // Método para obtener solo los tipos activos
    public function getActivePropertyTypes()
    {
        $query = "SELECT * FROM {$this->table_name} WHERE active = 1 ORDER BY name";
        parent::getAll($query);
        return $this;
    }
}