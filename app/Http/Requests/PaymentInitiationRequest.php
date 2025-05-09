<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentInitiationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'cart_id'        => 'required|integer|exists:carts,id',
            'payment_method' => 'required|in:paypal,stripe',
        ];
    }

    /**
     * Get the validated data for the payment initiation.
     *
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        return [
            'cart_id'        => $this->input('cart_id'),
            'payment_method' => $this->input('payment_method'),
            'user_id'        => $this->user()->id,
        ];
    }
}
