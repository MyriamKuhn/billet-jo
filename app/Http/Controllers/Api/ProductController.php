<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Services\ProductFilterService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\Product;

class ProductController extends Controller
{
    protected ProductFilterService $filterService;

    public function __construct(ProductFilterService $filterService) {
        $this->filterService = $filterService;
    }

    /**
     * Show the list of available products with optional filters and sorting.
     *
     * @OA\Get(
     *     path="/api/products",
     *     summary="Get filtered list of products",
     *     description="Returns a paginated and filtered list of available products according to query parameters. Cache is used to optimize performance.",
     *     operationId="index",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Filter products by name",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter products by category",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         description="Filter products by location",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter products by event date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="places",
     *         in="query",
     *         description="Filter products by minimum number of available places",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort products by a specific field (name, price, product_details->date)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "product_details->date"})
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of products per page (default 10)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="The page number for pagination (default 1)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *   @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cérémonie d’ouverture officielle des JO"),
     *                     @OA\Property(property="price", type="string", example="100.00"),
     *                     @OA\Property(property="sale", type="string", example="0.00"),
     *                     @OA\Property(property="stock_quantity", type="integer", example=50),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-30T19:54:25.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-04-30T19:54:25.000000Z"),
     *                     @OA\Property(
     *                         property="product_details",
     *                         type="object",
     *                         @OA\Property(property="places", type="integer", example=1),
     *                         @OA\Property(property="description", type="array", items=@OA\Items(type="string"), example={
     *                             "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
     *                             "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière."
     *                         }),
     *                         @OA\Property(property="date", type="string", format="date", example="2024-07-26"),
     *                         @OA\Property(property="time", type="string", example="19h30 (accès recommandé dès 18h00)"),
     *                         @OA\Property(property="location", type="string", example="Stade de France, Saint-Denis"),
     *                         @OA\Property(property="category", type="string", example="Cérémonies"),
     *                         @OA\Property(property="image", type="string", example="https://picsum.photos/seed/1/600/400")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (invalid or unexpected parameters)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Unexpected parameter(s) detected: foo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="No products found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An error occurred while fetching the products. Please try again later.")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Verify if the request contains any unexpected parameters
            $allowedParams = ['name', 'category', 'location', 'date', 'places', 'sort_by', 'order', 'per_page', 'page'];
            $extraParams = array_diff(array_keys($request->all()), $allowedParams);

            if (!empty($extraParams)) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('product.error_unexpected_parameter', ['params' => implode(', ', $extraParams)]),
                    'allowed_parameters' => $allowedParams
                ], 400);
            }

            // Validate the request parameters
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'date' => 'nullable|date_format:Y-m-d',
                'places' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|in:name,price,product_details->date',
                'order' => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page'=> 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $validated = $validator->validated();

            // Generate a unique cache key based on the request parameters
            $cacheKey = 'products_' . md5(serialize($validated));

            // Check if the products are already cached and return them if available
            $products = Cache::store('redis')->remember($cacheKey, 60, function () use ($validated) {
                $query = $this->filterService->buildQuery($validated);
                $perPage = $validated['per_page'] ?? 10;
                return $query->paginate($perPage);
            });

            // Check if products are empty
            if ($products->count() === 0) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('product.error_no_product_found')
                ], 404);
            }

            return response()->json([
                'status'=> 'success',
                'message' => __('product.products_retrieved'),
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching products for page: ' . $e->getMessage());

            // Return a generic error message with HTTP status 500
            return response()->json([
                'status' => 'error',
                'error' => __('product.error_fetch_product')
            ], 500);  // Code HTTP 500 Internal Server Error
        }
    }

    /**
     * Show the list of all products with optional filters and sorting. Only for admin.
     *
     * @OA\Get(
     *     path="/api/products/all",
     *     summary="Get filtered list of all products (for admin only)",
     *     description="Returns a paginated and filtered list of all products. Cache is used to optimize performance. Only for admin.",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         description="Filter products by name",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="category",
     *         in="query",
     *         description="Filter products by category",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="location",
     *         in="query",
     *         description="Filter products by location",
     *         required=false,
     *         @OA\Schema(type="string", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="date",
     *         in="query",
     *         description="Filter products by event date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="places",
     *         in="query",
     *         description="Filter products by minimum number of available places",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort products by a specific field (name, price, product_details->date)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "product_details->date"})
     *     ),
     *     @OA\Parameter(
     *         name="order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of products per page (default 10)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="The page number for pagination (default 1)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1)
     *     ),
     *   @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Products retrieved successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Cérémonie d’ouverture officielle des JO"),
     *                     @OA\Property(property="price", type="string", example="100.00"),
     *                     @OA\Property(property="sale", type="string", example="0.00"),
     *                     @OA\Property(property="stock_quantity", type="integer", example=50),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-04-30T19:54:25.000000Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-04-30T19:54:25.000000Z"),
     *                     @OA\Property(
     *                         property="product_details",
     *                         type="object",
     *                         @OA\Property(property="places", type="integer", example=1),
     *                         @OA\Property(property="description", type="array", items=@OA\Items(type="string"), example={
     *                             "Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024.",
     *                             "Vivez une soirée exceptionnelle où le sport, la culture et l’émotion se rencontrent dans un spectacle grandiose au cœur de la Ville Lumière."
     *                         }),
     *                         @OA\Property(property="date", type="string", format="date", example="2024-07-26"),
     *                         @OA\Property(property="time", type="string", example="19h30 (accès recommandé dès 18h00)"),
     *                         @OA\Property(property="location", type="string", example="Stade de France, Saint-Denis"),
     *                         @OA\Property(property="category", type="string", example="Cérémonies"),
     *                         @OA\Property(property="image", type="string", example="https://picsum.photos/seed/1/600/400")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer", example=100),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="last_page", type="integer", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request (invalid or unexpected parameters)",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="Unexpected parameter(s) detected: foo")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No products found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="No products found.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="error", type="string", example="An error occurred while fetching the products. Please try again later.")
     *         )
     *     )
     * )
     */
    public function getProducts(Request $request)
    {
        try {
            // Only for the admin
            $user = auth()->user();

            if (!$user || !$user->role->isAdmin()) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('product.error_not_authorized')
                ], 403);
            }

            // Verify if the request contains any unexpected parameters
            $allowedParams = ['name', 'category', 'location', 'date', 'places', 'sort_by', 'order', 'per_page', 'page'];
            $extraParams = array_diff(array_keys($request->all()), $allowedParams);

            if (!empty($extraParams)) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('product.error_unexpected_parameter', ['params' => implode(', ', $extraParams)]),
                    'allowed_parameters' => $allowedParams
                ], 400);
            }

            // Validate the request parameters
            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'category' => 'nullable|string|max:255',
                'location' => 'nullable|string|max:255',
                'date' => 'nullable|date_format:Y-m-d',
                'places' => 'nullable|integer|min:1',
                'sort_by' => 'nullable|in:name,price,product_details->date',
                'order' => 'nullable|in:asc,desc',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page'=> 'nullable|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'error' => $validator->errors()->first()
                ], 400);
            }

            $validated = $validator->validated();

            // Generate a unique cache key based on the request parameters
            $cacheKey = 'products_all_' . md5(serialize($validated));

            // Check if the products are already cached and return them if available
            $products = Cache::store('redis')->remember($cacheKey, 60, function () use ($validated) {
                $query = $this->filterService->buildQuery($validated, false);
                $perPage = $validated['per_page'] ?? 10;
                return $query->paginate($perPage);
            });

            // Check if products are empty
            if ($products->count() === 0) {
                return response()->json([
                    'status' => 'error',
                    'error' => __('product.error_no_product_found')
                ], 404);
            }

            return response()->json([
                'status'=> 'success',
                'message' => __('product.products_retrieved'),
                'data' => $products->items(),
                'pagination' => [
                    'total' => $products->total(),
                    'per_page' => $products->perPage(),
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Error fetching products for admin: ' . $e->getMessage());

            // Return a generic error message with HTTP status 500
            return response()->json([
                'status' => 'error',
                'error' => __('product.error_fetch_product')
            ], 500);  // Code HTTP 500 Internal Server Error
        }
    }

    /**
     * @OA\Get(
     *     path="/api/products/{product}",
     *     summary="Show a specific product by ID",
     *     description="Returns the details of a specific product by its ID.",
     *     operationId="getProductById",
     *     tags={"Products"},
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="ID du produit",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Billet concert"),
     *                 @OA\Property(
     *                     property="product_details",
     *                     type="object",
     *                     @OA\Property(property="places", type="integer", example=1),
     *                     @OA\Property(property="description", type="string", example="Assistez à un moment historique avec la cérémonie d’ouverture des Jeux Olympiques de Paris 2024. Vivez une soirée exceptionnelle..."),
     *                     @OA\Property(property="date", type="string", example="2024-07-26"),
     *                     @OA\Property(property="time", type="string", example="19h30 (accès recommandé dès 18h00)"),
     *                     @OA\Property(property="location", type="string", example="Stade de France, Saint-Denis"),
     *                     @OA\Property(property="category", type="string", example="Cérémonies"),
     *                     @OA\Property(property="image", type="string", example="https://picsum.photos/seed/1/600/400")
     *                 ),
     *                 @OA\Property(property="price", type="number", format="float", example=59.99),
     *                 @OA\Property(property="sale", type="number", format="float", example=49.99),
     *                 @OA\Property(property="stock_quantity", type="integer", example=100),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-04-01T12:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Produit non trouvé",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\Models\Product] 123")
     *         )
     *     )
     * )
     */
    public function show(Product $product)
    {
        return response()->json([
            'status' => 'success',
            'message'=> __('product.product_retrieved'),
            'data' => $product,
        ]);
    }
}
