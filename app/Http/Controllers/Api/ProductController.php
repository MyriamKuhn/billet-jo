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
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\UpdateProductPricingRequest;

/**
 * Controller for product-related API endpoints.
 *
 * Supports:
 * - Listing in‑stock products (public)
 * - Listing all products (admin only)
 * - Retrieving a single product
 * - Creating a new product (admin only)
 * - Updating product details and images (admin only)
 * - Updating pricing and stock (admin only)
 */
class ProductController extends Controller
{
    /**
     * Service for handling public product listing logic.
     *
     * @var ProductListingService
     */
    protected ProductListingService $listingService;
    /**
     * Service for handling admin product creation and updates.
     *
     * @var ProductManagementService
     */
    protected ProductManagementService $productService;

    /**
     * Inject services for listing and managing products.
     *
     * @param  ProductListingService     $listingService  Business logic for listing
     * @param  ProductManagementService  $productService  Business logic for create/update
     */
    public function __construct(ProductListingService $listingService, ProductManagementService $productService) {
        $this->listingService = $listingService;
        $this->productService = $productService;
    }

    /**
     * Show a paginated list of in‑stock products with optional filters and sorting.
     *
     * Aborts with 404 if no products match.
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
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
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
     * @param  IndexProductRequest  $request  Validated filter & pagination input
     * @return JsonResponse                   JSON containing `data` and `pagination`
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
     * Show a paginated list of all products (including out‑of‑stock) for admins.
     *
     * Aborts with 404 if no products match.
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
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
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
     * @param  AdminProductRequest  $request  Validated filter & pagination input
     * @return JsonResponse                   JSON containing `data` and `pagination`
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
     * Retrieve the details of a single product by ID.
     *
     * @OA\Get(
     *     path="/api/products/{product}",
     *     operationId="getProductById",
     *     tags={"Products"},
     *     summary="Get product details",
     *     description="Returns the full details of a single product identified by its ID.",
     *
     *     @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
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
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  Product     $product  The product model resolved by route model binding
     * @return JsonResponse          JSON containing the `data` property with product details
     */
    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product),
        ], 200);
    }

    /**
     * Create a new product with translations and optional image (admin only).
     *
     * Stores uploaded image, injects filename into all locales,
     * and delegates creation to the management service.
     *
     * @OA\Post(
     *   path="/api/products",
     *   operationId="createProduct",
     *   tags={"Products"},
     *   summary="Create a new product with translations and images",
     *   description="Creates a new product record in **3** languages (en, fr, de) with images upload for each languages.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(ref="#/components/parameters/AcceptLanguageHeader"),
     *
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={
     *           "price",
     *           "stock_quantity",
     *           "image",
     *           "translations[en][name]",
     *           "translations[fr][name]",
     *           "translations[de][name]",
     *           "translations[en][product_details][places]",
     *           "translations[fr][product_details][places]",
     *           "translations[de][product_details][places]",
     *           "translations[en][product_details][description]",
     *           "translations[fr][product_details][description]",
     *           "translations[de][product_details][description]",
     *           "translations[en][product_details][date]",
     *           "translations[fr][product_details][date]",
     *           "translations[de][product_details][date]",
     *           "translations[en][product_details][time]",
     *           "translations[fr][product_details][time]",
     *           "translations[de][product_details][time]",
     *           "translations[en][product_details][location]",
     *           "translations[fr][product_details][location]",
     *           "translations[de][product_details][location]",
     *           "translations[en][product_details][category]",
     *           "translations[fr][product_details][category]",
     *           "translations[de][product_details][category]",
     *
     *         },
     *
     *         @OA\Property(property="price",          type="number",  format="float", example=100.00),
     *         @OA\Property(property="sale",           type="number",  format="float", example=0.10),
     *         @OA\Property(property="stock_quantity", type="integer", example=50),
     *         @OA\Property(property="image",          type="file", format="binary", description="Single image file applied to all translations"),
     *
     *         @OA\Property(property="translations[en][name]",           type="string",  example="Opening Ceremony"),
     *         @OA\Property(property="translations[en][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[en][product_details][description]", type="string",  example="Experience the opening…"),
     *         @OA\Property(property="translations[en][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[en][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[en][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[en][product_details][category]",    type="string",  example="Ceremonies"),
     *
     *         @OA\Property(property="translations[fr][name]",           type="string",  example="Cérémonie d’ouverture"),
     *         @OA\Property(property="translations[fr][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[fr][product_details][description]", type="string",  example="Assistez à la cérémonie…"),
     *         @OA\Property(property="translations[fr][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[fr][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[fr][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[fr][product_details][category]",    type="string",  example="Cérémonies"),
     *
     *         @OA\Property(property="translations[de][name]",           type="string",  example="Eröffnungszeremonie"),
     *         @OA\Property(property="translations[de][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[de][product_details][description]", type="string",  example="Erleben Sie die Eröffnung…"),
     *         @OA\Property(property="translations[de][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[de][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[de][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[de][product_details][category]",    type="string",  example="Zeremonien"),
     *       )
     *     )
     *   ),
     *
     *   @OA\Response(response=201, description="Product created successfully, no content"),
     *   @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *   @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *   @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *   @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *   @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  StoreProductRequest  $request  Validated input including translations & image
     * @return JsonResponse                  201 Created with no body
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            /** @var \Illuminate\Http\UploadedFile $image */
            $image    = $request->file('image');

            $filename = $image->store('', 'images');

            foreach (['en','fr','de'] as $locale) {
                $data['translations'][$locale]['product_details']['image'] = $filename;
            }
        }

        $product = $this->productService->create($data);

        return response()->json(null, 201);
    }

    /**
     * Update an existing product’s details and image (admin only).
     *
     * - Retains old image if none uploaded
     * - Deletes old file and stores new one if provided
     * - Delegates the update to the management service
     *
     * @OA\Post(
     *     path="/api/products/{product}",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     summary="Update product details",
     *     description="
Updates the details of an existing product for all 3 languages.

**Requirements**:
- Bearer authentication (admin only)
- **multipart/form-data** content type
- The image file is optional
- The old image will be deleted if a new one is provided.
- The product ID must exist in the database.
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
     *   @OA\RequestBody(
     *     required=true,
     *     @OA\MediaType(
     *       mediaType="multipart/form-data",
     *       @OA\Schema(
     *         required={
     *           "price",
     *           "stock_quantity",
     *           "image",
     *           "translations[en][name]",
     *           "translations[fr][name]",
     *           "translations[de][name]",
     *           "translations[en][product_details][places]",
     *           "translations[fr][product_details][places]",
     *           "translations[de][product_details][places]",
     *           "translations[en][product_details][description]",
     *           "translations[fr][product_details][description]",
     *           "translations[de][product_details][description]",
     *           "translations[en][product_details][date]",
     *           "translations[fr][product_details][date]",
     *           "translations[de][product_details][date]",
     *           "translations[en][product_details][time]",
     *           "translations[fr][product_details][time]",
     *           "translations[de][product_details][time]",
     *           "translations[en][product_details][location]",
     *           "translations[fr][product_details][location]",
     *           "translations[de][product_details][location]",
     *           "translations[en][product_details][category]",
     *           "translations[fr][product_details][category]",
     *           "translations[de][product_details][category]",
     *         },
     *
     *         @OA\Property(property="price",          type="number",  format="float", example=100.00),
     *         @OA\Property(property="sale",           type="number",  format="float", example=0.10),
     *         @OA\Property(property="stock_quantity", type="integer", example=50),
     *         @OA\Property(property="image",          type="file", format="binary", description="Single image file applied to all translations"),
     *
     *         @OA\Property(property="translations[en][name]",           type="string",  example="Opening Ceremony"),
     *         @OA\Property(property="translations[en][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[en][product_details][description]", type="string",  example="Experience the opening…"),
     *         @OA\Property(property="translations[en][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[en][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[en][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[en][product_details][category]",    type="string",  example="Ceremonies"),
     *
     *         @OA\Property(property="translations[fr][name]",           type="string",  example="Cérémonie d’ouverture"),
     *         @OA\Property(property="translations[fr][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[fr][product_details][description]", type="string",  example="Assistez à la cérémonie…"),
     *         @OA\Property(property="translations[fr][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[fr][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[fr][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[fr][product_details][category]",    type="string",  example="Cérémonies"),
     *
     *         @OA\Property(property="translations[de][name]",           type="string",  example="Eröffnungszeremonie"),
     *         @OA\Property(property="translations[de][product_details][places]",      type="integer", example=1),
     *         @OA\Property(property="translations[de][product_details][description]", type="string",  example="Erleben Sie die Eröffnung…"),
     *         @OA\Property(property="translations[de][product_details][date]",        type="string",  format="date", example="2024-07-26"),
     *         @OA\Property(property="translations[de][product_details][time]",        type="string",  example="19:30"),
     *         @OA\Property(property="translations[de][product_details][location]",    type="string",  example="Stade de France"),
     *         @OA\Property(property="translations[de][product_details][category]",    type="string",  example="Zeremonien"),
     *       )
     *     )
     *   ),
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
     * @param  StoreProductRequest  $request  Validated input including optional image
     * @param  Product              $product  The product to update
     * @return JsonResponse                  204 No Content on success
     */
    public function update(StoreProductRequest $request, Product $product): JsonResponse
    {
        $data = $request->validated();

        $oldFilename = data_get($product->product_details, 'image');

        if (! $request->hasFile('image')) {
            foreach (['en','fr','de'] as $locale) {
                $data['translations'][$locale]['product_details']['image'] = $oldFilename;
            }
        }

        if ($request->hasFile('image')) {
            /** @var \Illuminate\Http\UploadedFile $image */
            $image    = $request->file('image');
            $oldImage = data_get($product->product_details, 'image');

            if ($oldImage) {
                Storage::disk('images')->delete($oldImage);
            }

            $filename = $image->store('', 'images');

            foreach (['en', 'fr', 'de'] as $locale) {
                $data['translations'][$locale]['product_details']['image'] = $filename;
            }
        }

        $updated = $this->productService->update($product, $data);

        return response()->json(null, 204);
    }

    /**
     * Update only the pricing and stock quantity of a product (admin only).
     *
     * @OA\Patch(
     *     path="/api/products/{product}/pricing",
     *     operationId="updateProductPricing",
     *     tags={"Products"},
     *     summary="Update product pricing and stock (admin only)",
     *     description="Updates the pricing and stock quantity of a product only for an administrator.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="product", in="path", required=true,
     *         @OA\Schema(type="integer", format="int64", example=42),
     *         description="ID du produit à mettre à jour"
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="price",          type="number", format="float", example=59.99),
     *             @OA\Property(property="sale",           type="number", format="float", example=0.15),
     *             @OA\Property(property="stock_quantity", type="integer",               example=120)
     *         )
     *     ),
     *
     *     @OA\Response(response=204, description="Product pricing updated successfully, no content"),
     *     @OA\Response(response=400, ref="#/components/responses/BadRequest"),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, ref="#/components/responses/Forbidden"),
     *     @OA\Response(response=404, ref="#/components/responses/NotFound"),
     *     @OA\Response(response=422, ref="#/components/responses/ValidationError"),
     *     @OA\Response(response=500, ref="#/components/responses/InternalError")
     * )
     *
     * @param  UpdateProductPricingRequest  $request  Validated `price`, `sale`, `stock_quantity`
     * @param  Product                      $product  The product to update
     * @return JsonResponse                            204 No Content on success
     */
    public function updatePricing(UpdateProductPricingRequest $request, Product $product): JsonResponse
    {
        $this->productService->updatePricing($product, $request->validated());

        return response()->json(null, 204);
    }
}
