<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate changes to a product's pricing and stock quantity (admin only).
 *
 * Allows an administrator to optionally update:
 * - price: the new unit price (must be non-negative)
 * - sale: the new discount rate (must be non-negative)
 * - stock_quantity: the new stock level (must be non-negative integer)
 */
class UpdateProductPricingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated admin users may modify product pricing or stock.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * - price: optional numeric, must be ≥ 0
     * - sale: optional numeric, must be ≥ 0
     * - stock_quantity: optional integer, must be ≥ 0
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'price'          => ['sometimes','numeric','min:0'],
            'sale'           => ['sometimes','numeric','min:0'],
            'stock_quantity' => ['sometimes','integer','min:0'],
        ];
    }
}
