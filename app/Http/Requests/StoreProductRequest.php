<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *   schema="StoreProduct",
 *   title="StoreProduct",
 *   type="object",
 *   required={"name","price","stock_quantity","product_details"},
 *   @OA\Property(property="name",           type="string",  example="Sample Product"),
 *   @OA\Property(property="price",          type="number",  format="float", example=49.99),
 *   @OA\Property(property="sale",           type="number",  format="float", example=39.99),
 *   @OA\Property(property="stock_quantity", type="integer", example=100),
 *   @OA\Property(
 *     property="product_details",
 *     ref="#/components/schemas/ProductDetails"
 *   )
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
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_details' => ['required', 'array'],
            'product_details.places' => ['required', 'integer', 'min:1'],
            'product_details.description' => ['required', 'string'],
            'product_details.date' => ['required', 'date_format:Y-m-d'],
            'product_details.time' => ['required', 'string'],
            'product_details.location' => ['required', 'string'],
            'product_details.category' => ['required', 'string'],
            'product_details.image' => $imageRules,
        ];
    }
}
