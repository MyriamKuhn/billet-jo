<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate updating the quantity of an item in the cart.
 *
 * Applies to both guest and authenticated users.
 * Ensures the `quantity` field is present and is a non-negative integer.
 */
class UpdateCartItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Allow all users (guest or authenticated) to update cart items.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * - quantity: required, integer, minimum 0 (0 removes the item).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The product ID itself comes from the route; only validate the new quantity here.
            'quantity' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Custom error messages for validation failures.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.min' => 'Quantity must be zero or a positive integer.',
        ];
    }
}
