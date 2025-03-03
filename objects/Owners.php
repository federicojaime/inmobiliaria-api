<?php

namespace objects;

use objects\Base;

class Owners extends Base
{
    private $table_name = "owners";

    public function __construct($db)
    {
        parent::__construct($db);
    }

    public function getOwners()
    {
        $query = "SELECT * FROM {$this->table_name} ORDER BY name";
        parent::getAll($query);
        return $this;
    }

    public function getOwner($id)
    {
        $query = "SELECT * FROM {$this->table_name} WHERE id = :id";
        parent::getOne($query, ["id" => $id]);
        return $this;
    }

    public function getOwnerByDocument($document_type, $document_number)
    {
        $query = "SELECT * FROM {$this->table_name} WHERE document_type = :document_type AND document_number = :document_number";
        parent::getOne($query, [
            "document_type" => $document_type,
            "document_number" => $document_number
        ]);
        return $this;
    }

    public function addOwner($data)
    {
        $query = "INSERT INTO {$this->table_name} SET
                 document_type = :document_type,
                 document_number = :document_number,
                 name = :name,
                 email = :email,
                 phone = :phone,
                 address = :address,
                 city = :city,
                 province = :province,
                 is_company = :is_company";

        parent::add($query, [
            "document_type" => $data['document_type'],
            "document_number" => $data['document_number'],
            "name" => $data['name'],
            "email" => isset($data['email']) && !empty($data['email']) ? $data['email'] : null,
            "phone" => isset($data['phone']) && !empty($data['phone']) ? $data['phone'] : null,
            "address" => isset($data['address']) && !empty($data['address']) ? $data['address'] : null,
            "city" => isset($data['city']) && !empty($data['city']) ? $data['city'] : null,
            "province" => isset($data['province']) && !empty($data['province']) ? $data['province'] : null,
            "is_company" => isset($data['is_company']) && $data['is_company'] ? 1 : 0
        ]);
        return $this;
    }

    public function searchOwners($searchTerm)
    {
        $search = "%{$searchTerm}%";

        $query = "SELECT * FROM {$this->table_name} 
              WHERE name LIKE :search 
              OR document_number LIKE :search 
              OR email LIKE :search 
              OR phone LIKE :search 
              OR address LIKE :search 
              OR city LIKE :search 
              OR province LIKE :search
              ORDER BY name";

        parent::getAll($query, ["search" => $search]);
        return $this;
    }

    public function updateOwner($id, $data)
    {
        $query = "UPDATE {$this->table_name} SET
                 document_type = :document_type,
                 document_number = :document_number,
                 name = :name,
                 email = :email,
                 phone = :phone,
                 address = :address,
                 city = :city,
                 province = :province,
                 is_company = :is_company
                 WHERE id = :id";

        parent::update($query, [
            "id" => $id,
            "document_type" => $data['document_type'],
            "document_number" => $data['document_number'],
            "name" => $data['name'],
            "email" => isset($data['email']) && !empty($data['email']) ? $data['email'] : null,
            "phone" => isset($data['phone']) && !empty($data['phone']) ? $data['phone'] : null,
            "address" => isset($data['address']) && !empty($data['address']) ? $data['address'] : null,
            "city" => isset($data['city']) && !empty($data['city']) ? $data['city'] : null,
            "province" => isset($data['province']) && !empty($data['province']) ? $data['province'] : null,
            "is_company" => isset($data['is_company']) && $data['is_company'] ? 1 : 0
        ]);
        return $this;
    }

    public function deleteOwner($id)
    {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        parent::delete($query, ["id" => $id]);
        return $this;
    }
}
