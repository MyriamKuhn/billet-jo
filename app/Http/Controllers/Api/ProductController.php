<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductListingService;
use App\Services\ProductManagementService;
use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\AdminProductRequest;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    protected ProductListingService $listingService;
    protected ProductManagementService $productService;

    public function __construct(ProductListingService $listingService, ProductManagementService $productService) {
        $this->listingService = $listingService;
        $this->productService = $productService;
    }

    /**
     * Show the list of available products with optional filters and sorting.
     *
     *
     * @OA\Get(
     *     path="/api/products",
     *     operationId="getProductsList",
     *     tags={"Products"},
     *     summary="Retrieve a paginated list of in-stock products",
     *     description="
Returns a paginated list of products that are currently in stock.

**Optional query parameters**:
- `name`       — filter by product name
- `category`   — filter by category
- `location`   — filter by location
- `date`       — filter by date (YYYY-MM-DD)
- `places`     — filter by minimum number of places
- `sort_by`    — sort field (`name`, `price`, `product_details->date`)
- `order`      — sort direction (`asc`, `desc`)
- `per_page`   — items per page (default: 15)
- `page`       — page number (default: 1)
",
     *
     *     @OA\Parameter(name="name",     in="query", description="Filter by product name",     @OA\Schema(type="string")),
     *     @OA\Parameter(name="category", in="query", description="Filter by category",        @OA\Schema(type="string")),
     *     @OA\Parameter(name="location", in="query", description="Filter by location",        @OA\Schema(type="string")),
     *     @OA\Parameter(name="date",     in="query", description="Filter by date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="places",   in="query", description="Filter by minimum places",  @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="sort_by",  in="query", description="Sort field",              @OA\Schema(type="string", enum={"name","price","product_details->date"})),
     *     @OA\Parameter(name="order",    in="query", description="Sort direction",          @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page",          @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page",     in="query", description="Page number",             @OA\Schema(type="integer", default=1)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/MinimalProduct")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  IndexProductRequest  $request
     * @return JsonResponse
     */
    public function index(IndexProductRequest $request): JsonResponse
    {
        $result = $this->listingService->handle($request->validated(), true);

        if ($result->isEmpty()) {
            abort(404);
        }

        return response()->json([
            'data'       => ProductResource::collection($result)->resolve(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 200);
    }

    /**
     * Show a detailed paginated list of all products (admin only).
     *
     * @OA\Get(
     *     path="/api/products/all",
     *     summary="Retrieve all products (admin only)",
 *     description="
Returns a paginated list of all products (including out-of-stock), with optional filtering and sorting.

- **Caching enabled** for better performance
- **Authentication**: Bearer token (admin only)
- **Same query parameters** as `/api/products`
",
     *     operationId="getProducts",
     *     tags={"Products"},
     *     security={{"bearerAuth": {}}},
     *
     *     @OA\Parameter(name="name",     in="query", description="Filter by product name",     @OA\Schema(type="string")),
     *     @OA\Parameter(name="category", in="query", description="Filter by category",        @OA\Schema(type="string")),
     *     @OA\Parameter(name="location", in="query", description="Filter by location",        @OA\Schema(type="string")),
     *     @OA\Parameter(name="date",     in="query", description="Filter by date (YYYY-MM-DD)", @OA\Schema(type="string", format="date")),
     *     @OA\Parameter(name="places",   in="query", description="Filter by minimum places",  @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="sort_by",  in="query", description="Sort field",              @OA\Schema(type="string", enum={"name","price","product_details->date"})),
     *     @OA\Parameter(name="order",    in="query", description="Sort direction",          @OA\Schema(type="string", enum={"asc","desc"})),
     *     @OA\Parameter(name="per_page", in="query", description="Items per page",          @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="page",     in="query", description="Page number",             @OA\Schema(type="integer", default=1)),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Products retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/MinimalProduct")
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError"),
     *     @OA\Response(response=503, ref="#/components/responses/ServiceUnavailable")
     *   )
     *
     * @param  AdminProductRequest  $request
     * @return JsonResponse
     */
    public function getProducts(AdminProductRequest $request): JsonResponse
    {
        $result = $this->listingService->handle($request->validated(), false);

        if ($result->isEmpty()) {
            abort(404);
        }

        return response()->json([
            'data'       => ProductResource::collection($result)->resolve(),
            'pagination' => [
                'total'        => $result->total(),
                'per_page'     => $result->perPage(),
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
            ],
        ], 200);
    }

    /**
     * Retrieve a single product by its ID.
     *
     * @OA\Get(
     *     path="/api/products/{product}",
     *     operationId="getProductById",
     *     tags={"Products"},
     *     summary="Get product details",
     *     description="Returns the full details of a single product identified by its ID.",
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="ID of product to retrieve",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/MinimalProduct")
     *         )
     *     ),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  Product  $product
     * @return JsonResponse
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Create a new product (admin only).
     *
     * @OA\Post(
     *     path="/api/products",
     *     operationId="createProduct",
     *     tags={"Products"},
     *     summary="Create a new product",
     *     description="
Creates a new product record.

**Requirements**:
- Bearer authentication (admin only)
- Valid payload per `StoreProduct` schema
",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreProduct")
     *     ),
     *
     *     @OA\Response(response=201, description="Product created successfully, no content"),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  StoreProductRequest  $request
     * @return JsonResponse
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = $this->productService->create($request->validated());

        return response()->json(null, 201);
    }

    /**
     * Update an existing product (admin only).
     *
     * @OA\Put(
     *     path="/api/products/{product}",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     summary="Update product details",
     *     description="
Updates the details of an existing product.

**Requirements**:
- Bearer authentication (admin only)
- Valid payload per `StoreProduct` schema
",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="ID of the product to update",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/StoreProduct")
     *     ),
     *
     *     @OA\Response(response=204, description="Product updated successfully, no content"),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  StoreProductRequest  $request
     * @param  Product  $product
     * @return JsonResponse
     */
    public function update(StoreProductRequest $request, Product $product):JsonResponse
    {
        $updated = $this->productService->update($product, $request->validated());

        return response()->json(null, 204);
    }
}
