<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate data for creating or updating a product in all locales.
 *
 * Ensures that only administrators may perform the operation, normalizes incoming
 * numeric and array values, and enforces rules on common fields as well as per-locale
 * translation entries.
 *
 * @OA\Schema(
 *   schema="StoreProductSingleLocale",
 *   type="object",
 *   required={"name","price","stock_quantity","product_details"},
 *   @OA\Property(property="name",           type="string",  example="Billet concert"),
 *   @OA\Property(property="price",          type="number",  format="float", example=49.99),
 *   @OA\Property(property="sale",           type="number",  format="float", example=39.99),
 *   @OA\Property(property="stock_quantity", type="integer", example=100),
 *   @OA\Property(
 *     property="product_details",
 *     ref="#/components/schemas/StoreProductDetailsSingleLocale"
 *   )
 * )
 *
 * @OA\Schema(
 *   schema="StoreProductDetailsSingleLocale",
 *   type="object",
 *   required={"places","description","date","time","location","category","image_url"},
 *   @OA\Property(property="places",      type="integer", example=2),
 *   @OA\Property(property="description", type="string",  example="Description détaillée…"),
 *   @OA\Property(property="date",        type="string",  format="date", example="2024-07-26"),
 *   @OA\Property(property="time",        type="string",  example="19:30"),
 *   @OA\Property(property="location",    type="string",  example="Stade de France"),
 *   @OA\Property(property="category",    type="string",  example="Cérémonies"),
 *   @OA\Property(property="image_url",   type="string",  format="url",  example="https://…/image.jpg")
 * )
 */
class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated admins may create or update products.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Prepare the data for validation.
     *
     * - Casts price and sale to floats (sale may be empty string ➔ null).
     * - Casts stock_quantity to integer.
     * - Casts each locale's `product_details.places` to integer for fr, en, de.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $input = $this->all();

        // Normalize top‑level numeric values
        if (isset($input['price'])) {
            $input['price'] = (float) $input['price'];
        }
        if (isset($input['sale'])) {
            $input['sale'] = $input['sale'] === '' ? null : (float) $input['sale'];
        }
        if (isset($input['stock_quantity'])) {
            $input['stock_quantity'] = (int) $input['stock_quantity'];
        }

        // Normalize `places` under product_details for each locale
        if (isset($input['translations']) && is_array($input['translations'])) {
            foreach (['fr','en','de'] as $loc) {
                if (
                    isset($input['translations'][$loc]['product_details'])
                    && isset($input['translations'][$loc]['product_details']['places'])
                ) {
                    $input['translations'][$loc]['product_details']['places'] =
                        (int) $input['translations'][$loc]['product_details']['places'];
                }
            }
        }

        // Merge normalized values back into the request
        $this->merge($input);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * - Validates global product fields (price, sale, stock_quantity, image).
     * - Requires exactly three translations (fr, en, de), each with its own rules.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Shared image validation rules
        $imageRules = ['nullable','file','image','mimes:jpeg,png,jpg,gif,svg','max:2048'];

        return [
            // Top‑level product attributes
            'price'          => ['required', 'numeric', 'min:0'],
            'sale'           => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'image'          => $imageRules,

            // Require exactly three locales
            'translations'        => ['required','array','size:3'],
            'translations.en'     => ['required','array'],
            'translations.fr'     => ['required','array'],
            'translations.de'     => ['required','array'],

            // Common translation fields
            'translations.*.name'                               => ['required','string','max:255'],

            'translations.*.product_details'                    => ['required','array'],
            'translations.*.product_details.places'             => ['required','integer','min:1'],
            'translations.*.product_details.description'        => ['required','string'],
            'translations.*.product_details.date'               => ['required','date_format:Y-m-d'],
            'translations.*.product_details.time'               => ['required','string'],
            'translations.*.product_details.location'           => ['required','string'],
            'translations.*.product_details.category'           => ['required','string']
        ];
    }
}
