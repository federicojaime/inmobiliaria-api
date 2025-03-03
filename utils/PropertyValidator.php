<?php

namespace utils;

class PropertyValidator
{
    private $errors = [];

    public function validate($data, $isUpdate = false)
    {
        $this->errors = [];

        // Validar campos requeridos
        $requiredFields = [
            'title' => 'El título es requerido',
            'description' => 'La descripción es requerida',
            'type' => 'El tipo de propiedad es requerido',
            'status' => 'El estado es requerido',
            'covered_area' => 'La superficie cubierta es requerida',
            'total_area' => 'La superficie del terreno es requerida',
            'province' => 'La provincia es requerida'
        ];

        // En caso de creación, el propietario es obligatorio
        if (!$isUpdate) {
            $requiredFields['owner_id'] = 'El propietario es requerido';
        }

        foreach ($requiredFields as $field => $message) {
            if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '') || $data[$field] === null) {
                $this->errors[] = $message;
            }
        }

        // Validar que haya al menos un precio
        if ((!isset($data['price_ars']) || $data['price_ars'] === '' || $data['price_ars'] === null) &&
            (!isset($data['price_usd']) || $data['price_usd'] === '' || $data['price_usd'] === null)
        ) {
            $this->errors[] = "Debe especificar al menos un precio (ARS o USD)";
        }

        // Validar tipos de datos
        if (isset($data['price_ars']) && $data['price_ars'] !== '' && !is_numeric($data['price_ars'])) {
            $this->errors[] = "El precio en ARS debe ser un número";
        }

        if (isset($data['price_usd']) && $data['price_usd'] !== '' && !is_numeric($data['price_usd'])) {
            $this->errors[] = "El precio en USD debe ser un número";
        }

        if (isset($data['covered_area']) && !is_numeric($data['covered_area'])) {
            $this->errors[] = "La superficie cubierta debe ser un número";
        }

        if (isset($data['total_area']) && !is_numeric($data['total_area'])) {
            $this->errors[] = "La superficie del terreno debe ser un número";
        }

        if (isset($data['bedrooms']) && $data['bedrooms'] !== '' && !is_numeric($data['bedrooms'])) {
            $this->errors[] = "El número de habitaciones debe ser un número";
        }

        if (isset($data['bathrooms']) && $data['bathrooms'] !== '' && !is_numeric($data['bathrooms'])) {
            $this->errors[] = "El número de baños debe ser un número";
        }

        // Validar latitud y longitud
        if (isset($data['latitude']) && $data['latitude'] !== '' && (!is_numeric($data['latitude']) || $data['latitude'] < -90 || $data['latitude'] > 90)) {
            $this->errors[] = "La latitud debe ser un número válido entre -90 y 90";
        }

        if (isset($data['longitude']) && $data['longitude'] !== '' && (!is_numeric($data['longitude']) || $data['longitude'] < -180 || $data['longitude'] > 180)) {
            $this->errors[] = "La longitud debe ser un número válido entre -180 y 180";
        }

        // Si hay latitud, debe haber longitud y viceversa
        if ((isset($data['latitude']) && $data['latitude'] !== '' && (!isset($data['longitude']) || $data['longitude'] === '')) ||
            (isset($data['longitude']) && $data['longitude'] !== '' && (!isset($data['latitude']) || $data['latitude'] === ''))
        ) {
            $this->errors[] = "Si se proporciona una coordenada (latitud o longitud), se debe proporcionar la otra";
        }


        // Validar valores mínimos
        if (isset($data['covered_area']) && is_numeric($data['covered_area']) && $data['covered_area'] <= 0) {
            $this->errors[] = "La superficie cubierta debe ser mayor a 0";
        }

        if (isset($data['total_area']) && is_numeric($data['total_area']) && $data['total_area'] <= 0) {
            $this->errors[] = "La superficie del terreno debe ser mayor a 0";
        }

        // Validar que la superficie cubierta no sea mayor a la total
        if (
            isset($data['covered_area']) && isset($data['total_area']) &&
            is_numeric($data['covered_area']) && is_numeric($data['total_area']) &&
            $data['covered_area'] > $data['total_area']
        ) {
            $this->errors[] = "La superficie cubierta no puede ser mayor a la superficie total del terreno";
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

    public function getErrors()
    {
        return $this->errors;
    }

    public function getFormattedErrors()
    {
        $resp = new \stdClass();
        $resp->ok = false;
        $resp->msg = "Hay errores en los datos suministrados";
        $resp->data = null;
        $resp->errores = $this->errors;
        return $resp;
    }
}
