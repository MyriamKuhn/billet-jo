<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        $locale   = app()->getLocale();
        $perPage  = $filters['per_page'] ?? 15;
        $cacheKey = "products_{$locale}_"
                    . ($onlyAvailableStock ? '' : 'all_')
                    . md5(json_encode($filters));

        return Cache::store('redis')->remember(
            $cacheKey,
            now()->addSeconds(30),
            fn() => $this->buildQuery($filters, $onlyAvailableStock, $locale)->paginate($perPage)
        );
    }

    /**
     * Build an Eloquent query builder instance applying filters to the Product model.
     *
     * @param  array  $filters  Associative array of filter parameters.
     * @param  bool   $onlyAvailableStock  If true, only products with stock>0; if false, returns all.
     * @param  string $locale  The locale for product translations.
     * @return Builder  The query builder with applied conditions and sorting.
     */
    protected function buildQuery(array $filters, bool $onlyAvailableStock, string $locale): Builder
    {
        $query = Product::query()
            ->from('products')
            ->leftJoin('product_translations as pt', function($join) use ($locale) {
                $join->on('pt.product_id', '=', 'products.id')
                    ->where('pt.locale', '=', $locale);
            })
            ->select([
                'products.*',
                // fallback sur products.name si pt.name is null
                DB::raw("COALESCE(pt.name, products.name) as name"),
                // fallback sur products.product_details si pt.product_details is null
                DB::raw("COALESCE(pt.product_details, products.product_details) as product_details"),
            ]);

        if ($onlyAvailableStock) {
            $query->where('products.stock_quantity', '>', 0);
        }

        if (! empty($filters['name'])) {
            $query->whereRaw(
                "COALESCE(pt.name, products.name) LIKE ?",
                ["%{$filters['name']}%"]
            );
        }

        if (! empty($filters['category'])) {
            $query->whereRaw(
                "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pt.product_details, '$.category')), JSON_UNQUOTE(JSON_EXTRACT(products.product_details, '$.category'))) LIKE ?",
                ["%{$filters['category']}%"]
            );
        }

        if (! empty($filters['location'])) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(COALESCE(pt.product_details, products.product_details), '$.location')) LIKE ?",
                ["%{$filters['location']}%"]
            );
        }

        if (! empty($filters['date'])) {
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(COALESCE(pt.product_details, products.product_details), '$.date')) = ?",
                [$filters['date']]
            );
        }

        if (! empty($filters['places'])) {
            $query->whereRaw(
                "CAST(JSON_UNQUOTE(JSON_EXTRACT(COALESCE(pt.product_details, products.product_details), '$.places')) AS UNSIGNED) >= ?",
                [(int) $filters['places']]
            );
        }

        $sortBy = $filters['sort_by'] ?? 'name';
        $order  = $filters['order']   ?? 'asc';

        if (in_array($sortBy, ['name', 'price'])) {
            if ($sortBy === 'name') {
                $query->orderByRaw(
                    "COALESCE(pt.name, products.name) {$order}"
                );
            } else {
                $query->orderBy("products.{$sortBy}", $order);
            }
        } elseif ($sortBy === 'product_details->date') {
            $query->orderByRaw(
                "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(pt.product_details, '$.date')), JSON_UNQUOTE(JSON_EXTRACT(products.product_details, '$.date'))) {$order}"
            );
        }

        return $query;
    }
}
