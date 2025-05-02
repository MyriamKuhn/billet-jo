<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class ProductFilterService
{
    public function buildQuery(array $filters): Builder
    {
        $query = Product::query()->where('stock_quantity', '>', 0);

        if (isset($filters['name'])) {
            $query->where('name', 'LIKE', '%' . $filters['name'] . '%');
        }

        if (isset($filters['category'])) {
            $query->where('product_details->category', $filters['category']);
        }

        if (isset($filters['location'])) {
            $query->where('product_details->location', 'LIKE', '%' . $filters['location'] . '%');
        }

        if (isset($filters['date'])) {
            $query->where('product_details->date', $filters['date']);
        }

        if (isset($filters['places'])) {
            $query->where('product_details->places', '>=', (int) $filters['places']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $order = $filters['order'] ?? 'asc';

        if (in_array($sortBy, ['price', 'name'])) {
            $query->orderBy($sortBy, $order);
        } elseif ($sortBy === 'product_details->date') {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.date')) $order");
        }

        return $query;
    }
}
