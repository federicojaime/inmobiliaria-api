<?php
// services/FilterService.php
namespace services;

class FilterService
{
    public function buildPropertyFilters($params)
    {
        $conditions = [];
        $values = [];

        // Filtros de texto
        $textFilters = [
            'city' => 'p.city',
            'province' => 'p.province',
            'type' => 'p.type',
            'status' => 'p.status'
        ];
        foreach ($textFilters as $param => $field) {
            if (!empty($params[$param])) {
                $conditions[] = "$field = :$param";
                $values[$param] = $params[$param];
            }
        }

        // Filtro por tipo de propiedad (usando el ID)
        if (!empty($params['type_id'])) {
            $conditions[] = "p.type_id = :type_id";
            $values['type_id'] = intval($params['type_id']);
        }

        // Filtro por múltiples tipos de propiedad
        if (!empty($params['type_ids']) && is_array($params['type_ids'])) {
            $typeIds = array_filter($params['type_ids'], 'is_numeric');
            if (!empty($typeIds)) {
                $placeholders = [];
                foreach ($typeIds as $index => $typeId) {
                    $paramName = "type_id_" . $index;
                    $placeholders[] = ":$paramName";
                    $values[$paramName] = intval($typeId);
                }
                $conditions[] = "p.type_id IN (" . implode(', ', $placeholders) . ")";
            }
        }

        // Filtros de rango de precios y área
        $rangeFilters = [
            'min_price_ars' => ['field' => 'p.price_ars', 'operator' => '>='],
            'max_price_ars' => ['field' => 'p.price_ars', 'operator' => '<='],
            'min_price_usd' => ['field' => 'p.price_usd', 'operator' => '>='],
            'max_price_usd' => ['field' => 'p.price_usd', 'operator' => '<='],
            'min_covered_area' => ['field' => 'p.covered_area', 'operator' => '>='],
            'max_covered_area' => ['field' => 'p.covered_area', 'operator' => '<='],
            'min_total_area' => ['field' => 'p.total_area', 'operator' => '>='],
            'max_total_area' => ['field' => 'p.total_area', 'operator' => '<='],
            'min_latitude' => ['field' => 'p.latitude', 'operator' => '>='],
            'max_latitude' => ['field' => 'p.latitude', 'operator' => '<='],
            'min_longitude' => ['field' => 'p.longitude', 'operator' => '>='],
            'max_longitude' => ['field' => 'p.longitude', 'operator' => '<=']
        ];

        foreach ($rangeFilters as $param => $config) {
            if (!empty($params[$param])) {
                $conditions[] = "{$config['field']} {$config['operator']} :$param";
                $values[$param] = floatval($params[$param]);
            }
        }

        // Filtros booleanos
        $booleanFilters = [
            'featured' => 'p.featured',
            'garage' => 'p.garage',
            'has_electricity' => 'p.has_electricity',
            'has_natural_gas' => 'p.has_natural_gas',
            'has_sewage' => 'p.has_sewage',
            'has_paved_street' => 'p.has_paved_street'
        ];

        foreach ($booleanFilters as $param => $field) {
            if (isset($params[$param])) {
                $conditions[] = "$field = :$param";
                $values[$param] = filter_var($params[$param], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }

        // Filtros de amenities
        $amenityFilters = [
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

        foreach ($amenityFilters as $amenity) {
            if (isset($params[$amenity])) {
                $conditions[] = "pa.$amenity = :$amenity";
                $values[$amenity] = filter_var($params[$amenity], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
        }

        // Búsqueda por texto
        if (!empty($params['search'])) {
            $searchConditions = [
                "p.title LIKE :search",
                "p.description LIKE :search",
                "p.address LIKE :search",
                "p.city LIKE :search"
            ];
            $conditions[] = "(" . implode(" OR ", $searchConditions) . ")";
            $values['search'] = "%{$params['search']}%";
        }

        // Filtro por radio (basado en latitud y longitud)
        if (!empty($params['latitude']) && !empty($params['longitude']) && !empty($params['radius'])) {
            // Convertir radio de km a grados (aproximadamente)
            $radius_lat = floatval($params['radius']) / 111.32; // 1 grado ≈ 111.32 km
            $radius_lon = floatval($params['radius']) / (111.32 * cos(deg2rad(floatval($params['latitude'])))); // Ajustado por latitud

            // Calcular bounding box (cuadro delimitador) para optimizar la consulta
            $min_lat = floatval($params['latitude']) - $radius_lat;
            $max_lat = floatval($params['latitude']) + $radius_lat;
            $min_lon = floatval($params['longitude']) - $radius_lon;
            $max_lon = floatval($params['longitude']) + $radius_lon;

            // Filtrar primero por bounding box (más rápido que la fórmula haversine)
            $conditions[] = "p.latitude BETWEEN :min_lat AND :max_lat AND p.longitude BETWEEN :min_lon AND :max_lon";
            $values['min_lat'] = $min_lat;
            $values['max_lat'] = $max_lat;
            $values['min_lon'] = $min_lon;
            $values['max_lon'] = $max_lon;

            // Luego aplicar la fórmula haversine para una distancia más precisa
            $conditions[] = "(6371 * acos(cos(radians(:latitude)) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(:longitude)) + sin(radians(:latitude)) * sin(radians(p.latitude)))) <= :radius";
            $values['latitude'] = floatval($params['latitude']);
            $values['longitude'] = floatval($params['longitude']);
            $values['radius'] = floatval($params['radius']);
        }

        return [
            'conditions' => $conditions,
            'values' => $values
        ];
    }

    public function buildOrderBy($params)
    {
        $allowedFields = [
            'price_ars',
            'price_usd',
            'covered_area',
            'total_area',
            'created_at',
            'updated_at'
        ];

        $orderBy = [];

        if (!empty($params['sort_by']) && in_array($params['sort_by'], $allowedFields)) {
            $direction = !empty($params['sort_dir']) &&
                strtoupper($params['sort_dir']) === 'DESC' ? 'DESC' : 'ASC';
            $orderBy[] = "p.{$params['sort_by']} $direction";
        }

        // Orden por distancia si se proporciona latitud y longitud
        if (!empty($params['latitude']) && !empty($params['longitude'])) {
            $orderBy[] = "(6371 * acos(cos(radians(" . floatval($params['latitude']) . ")) * cos(radians(p.latitude)) * cos(radians(p.longitude) - radians(" . floatval($params['longitude']) . ")) + sin(radians(" . floatval($params['latitude']) . ")) * sin(radians(p.latitude)))) ASC";
        }

        // Orden por defecto
        if (empty($orderBy)) {
            $orderBy[] = "p.created_at DESC";
        }

        return implode(", ", $orderBy);
    }
}