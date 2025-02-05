<?php
namespace objects;

enum Registers {
    case One;
    case All;
    case Post;
    case Patch;
    case Delete;
};

class Base {
    protected $conn = null;
    protected $result = null;

    // constructor
    public function __construct($db) {
        if (!$db) {
            throw new \Exception("La conexión a la base de datos es nula");
        }
        $this->conn = $db;
        $this->result = new \stdClass();
        $this->reset();
    }

    private function reset() {
        $this->result->ok = true;
        $this->result->msg = "";
        $this->result->data = null;
    }

    private function execSql($query, Registers $resType, array $values = []) {
        $this->reset();
        try {
            $stmt = $this->conn->prepare($query);
            if(isset($values) && is_array($values) && !empty($values)) {
                foreach($values as $key => &$value) {                      
                    $stmt->bindParam(":" . $key, $value);
                }
            }
            $stmt->execute();
            switch($resType) {
                case Registers::One:
                    $this->result->data = $stmt->fetch(\PDO::FETCH_OBJ);
                    break;
                case Registers::All:
                    $this->result->data = $stmt->fetchAll(\PDO::FETCH_OBJ);
                    break;
                case Registers::Post:
                    $this->result->data = [ "newId" => $this->conn->lastInsertId() ];
                    break;
                default:
                    $this->result->data = null;
                    break;
            }
        } catch (\PDOException $e) {
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            $this->result->data = null;
        } catch (\Exception $e) {
            $this->result->ok = false;
            $this->result->msg = $e->getMessage();
            $this->result->data = null;
        }
    }

    public function getOne($query, array $values = []) {
        $this->execSql($query, Registers::One, $values);
    }

    public function getAll($query, array $values = []) {  // Añadido el parámetro $values
        $this->execSql($query, Registers::All, $values);
    }

    public function add($query, array $values) {
        $this->execSql($query, Registers::Post, $values);
    }

    public function update($query, array $values) {
        $this->execSql($query, Registers::Patch, $values);
    }

    public function delete($query, array $values) {
        $this->execSql($query, Registers::Delete, $values);
    }

    public function getResult() {
        return $this->result;
    }
}
?>