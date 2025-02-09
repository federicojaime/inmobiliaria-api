<?php
namespace utils;

class PropertyValidator {
    private $errors = [];
    
    public function validate($data) {
        $this->errors = [];

        // Validar campos requeridos
        $requiredFields = [
            'title' => 'El título es requerido',
            'description' => 'La descripción es requerida',
            'type' => 'El tipo de propiedad es requerido',
            'status' => 'El estado es requerido',
            'area_size' => 'El área es requerida',
            'province' => 'La provincia es requerida'
        ];

        foreach ($requiredFields as $field => $message) {
            if (empty($data[$field])) {
                $this->errors[] = $message;
            }
        }

        // Validar que haya al menos un precio
        if (empty($data['price_ars']) && empty($data['price_usd'])) {
            $this->errors[] = "Debe especificar al menos un precio (ARS o USD)";
        }

        // Validar tipos de datos
        if (!empty($data['price_ars']) && !is_numeric($data['price_ars'])) {
            $this->errors[] = "El precio en ARS debe ser un número";
        }

        if (!empty($data['price_usd']) && !is_numeric($data['price_usd'])) {
            $this->errors[] = "El precio en USD debe ser un número";
        }

        if (!empty($data['area_size']) && !is_numeric($data['area_size'])) {
            $this->errors[] = "El área debe ser un número";
        }

        if (!empty($data['bedrooms']) && !is_numeric($data['bedrooms'])) {
            $this->errors[] = "El número de habitaciones debe ser un número";
        }

        if (!empty($data['bathrooms']) && !is_numeric($data['bathrooms'])) {
            $this->errors[] = "El número de baños debe ser un número";
        }

        // Validar longitudes máximas
        $maxLengths = [
            'title' => 200,
            'address' => 255,
            'city' => 100,
            'province' => 100
        ];

        foreach ($maxLengths as $field => $maxLength) {
            if (!empty($data[$field]) && strlen($data[$field]) > $maxLength) {
                $this->errors[] = "El campo {$field} no debe exceder los {$maxLength} caracteres";
            }
        }

        return empty($this->errors);
    }

    public function getErrors() {
        return $this->errors;
    }

    public function getFormattedErrors() {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "Hay errores en los datos suministrados";
        $resp->data = null;
        $resp->errores = $this->errors;
        return $resp;
    }
}