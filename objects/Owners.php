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
            "email" => $data['email'] ?? null,
            "phone" => $data['phone'] ?? null,
            "address" => $data['address'] ?? null,
            "city" => $data['city'] ?? null,
            "province" => $data['province'] ?? null,
            "is_company" => $data['is_company'] ?? 0
        ]);
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

        parent::update($query, array_merge(
            ["id" => $id],
            [
                "document_type" => $data['document_type'],
                "document_number" => $data['document_number'],
                "name" => $data['name'],
                "email" => $data['email'] ?? null,
                "phone" => $data['phone'] ?? null,
                "address" => $data['address'] ?? null,
                "city" => $data['city'] ?? null,
                "province" => $data['province'] ?? null,
                "is_company" => $data['is_company'] ?? 0
            ]
        ));
        return $this;
    }

    public function deleteOwner($id)
    {
        $query = "DELETE FROM {$this->table_name} WHERE id = :id";
        parent::delete($query, ["id" => $id]);
        return $this;
    }
}
