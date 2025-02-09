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

        // Filtros de rango de precios
        $rangeFilters = [
            'min_price_ars' => ['field' => 'p.price_ars', 'operator' => '>='],
            'max_price_ars' => ['field' => 'p.price_ars', 'operator' => '<='],
            'min_price_usd' => ['field' => 'p.price_usd', 'operator' => '>='],
            'max_price_usd' => ['field' => 'p.price_usd', 'operator' => '<='],
            'min_area' => ['field' => 'p.area_size', 'operator' => '>='],
            'max_area' => ['field' => 'p.area_size', 'operator' => '<=']
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
            'garage' => 'p.garage'
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
            'area_size',
            'created_at',
            'updated_at'
        ];

        $orderBy = [];

        if (!empty($params['sort_by']) && in_array($params['sort_by'], $allowedFields)) {
            $direction = !empty($params['sort_dir']) &&
                strtoupper($params['sort_dir']) === 'DESC' ? 'DESC' : 'ASC';
            $orderBy[] = "p.{$params['sort_by']} $direction";
        }

        // Orden por defecto
        if (empty($orderBy)) {
            $orderBy[] = "p.created_at DESC";
        }

        return implode(", ", $orderBy);
    }
}
