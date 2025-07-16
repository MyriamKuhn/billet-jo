<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate payment initiation data.
 *
 * Ensures the user is authenticated and the provided cart ID and payment method
 * are valid for creating a new payment.
 */
class PaymentInitiationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the user is authenticated.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The cart identifier must be provided, a valid integer, and exist in carts table
            'cart_id'        => 'required|integer|exists:carts,id',
            // Payment method must be one of the supported options
            'payment_method' => 'required|in:paypal,stripe,free',
        ];
    }

    /**
     * Retrieve the validated data necessary for payment initiation.
     *
     * @return array<string, mixed>  Includes cart_id, payment_method, and user_id.
     */
    public function validatedData(): array
    {
        return [
            // The cart to be paid
            'cart_id'        => $this->input('cart_id'),
            // Selected payment provider
            'payment_method' => $this->input('payment_method'),
            // Associate the request with the authenticated user
            'user_id'        => $this->user()->id,
        ];
    }
}
