<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ProductListingService
{
    /**
     * Execute the product listing process: build the query, apply filters,
     * paginate results, and cache the outcome.
     *
     * @param  array  $filters  Associative array of filter parameters.
     *                          Supported keys: 'name', 'category', 'location',
     *                          'date', 'places', 'sort_by', 'order', 'per_page'.
     * @param  bool   $onlyAvailableStock  If true, only products with stock>0; if false, returns all.
     * @return LengthAwarePaginator  Paginated collection of Product models.
     */
    public function handle(array $filters, bool $onlyAvailableStock = true): LengthAwarePaginator
    {
        $perPage  = $filters['per_page'] ?? 15;
        $cacheKey = 'products_'
                    . ($onlyAvailableStock ? '' : 'all_')
                    . md5(json_encode($filters));

        return Cache::store('redis')->remember(
            $cacheKey,
            now()->addMinutes(60),
            fn() => $this->buildQuery($filters, $onlyAvailableStock)->paginate($perPage)
        );
    }

    /**
     * Build an Eloquent query builder instance applying filters to the Product model.
     *
     * @param  array  $filters  Associative array of filter parameters.
     * @param  bool   $onlyAvailableStock  If true, only products with stock>0; if false, returns all.
     * @return Builder  The query builder with applied conditions and sorting.
     */
    protected function buildQuery(array $filters, bool $onlyAvailableStock)
    {
        $query = Product::query();

        if ($onlyAvailableStock) {
            $query->where('stock_quantity', '>', 0);
        }
        if (!empty($filters['name'])) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }
        if (!empty($filters['category'])) {
            $query->where('product_details->category', $filters['category']);
        }
        if (!empty($filters['location'])) {
            $query->where('product_details->location', 'like', "%{$filters['location']}%");
        }
        if (!empty($filters['date'])) {
            $query->where('product_details->date', $filters['date']);
        }
        if (!empty($filters['places'])) {
            $query->where('product_details->places', '>=', (int) $filters['places']);
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $order  = $filters['order']   ?? 'asc';

        if (in_array($sortBy, ['name', 'price'])) {
            $query->orderBy($sortBy, $order);
        } elseif ($sortBy === 'product_details->date') {
            $query->orderByRaw("JSON_UNQUOTE(JSON_EXTRACT(product_details, '$.date')) {$order}");
        }

        return $query;
    }
}
