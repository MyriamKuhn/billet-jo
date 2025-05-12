<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
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
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $imageRules = $this->isMethod('post')
        ? ['required','file','image','mimes:jpeg,png,jpg,gif,svg','max:2048']
        : ['nullable','file','image','mimes:jpeg,png,jpg,gif,svg','max:2048'];

        return [
            // Global rules
            'price'          => ['required', 'numeric', 'min:0'],
            'sale'           => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],

            // Translations rules
            'translations'        => ['required','array','size:3'],
            'translations.en'     => ['required','array'],
            'translations.fr'     => ['required','array'],
            'translations.de'     => ['required','array'],

            // Translations fields
            'translations.*.name'                               => ['required','string','max:255'],

            'translations.*.product_details'                    => ['required','array'],
            'translations.*.product_details.places'             => ['required','integer','min:1'],
            'translations.*.product_details.description'        => ['required','string'],
            'translations.*.product_details.date'               => ['required','date_format:Y-m-d'],
            'translations.*.product_details.time'               => ['required','string'],
            'translations.*.product_details.location'           => ['required','string'],
            'translations.*.product_details.category'           => ['required','string'],
            'translations.*.product_details.image'              => $imageRules,
        ];
    }
}
