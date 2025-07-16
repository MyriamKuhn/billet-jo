<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate a ticket creation request for the authenticated user.
 *
 * Ensures that:
 * - The user is logged in.
 * - A valid `payment_uuid` is provided.
 * - The referenced payment exists and belongs to the authenticated user.
 */
class TicketStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated user may attempt to generate tickets for their own payment.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * - payment_uuid: required, must be a valid UUID, and must correspond
     *   to a payment record belonging to the current user.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_uuid' => [
                'required',
                'uuid',
                // Custom validation: ensure the payment belongs to the authenticated user
                function($attribute, $value, $fail) {
                    $payment = auth()->user()
                        ->payments()
                        ->where('uuid', $value)
                        ->first();

                    if (! $payment) {
                        $fail('Le paiement spécifié est invalide ou n\'appartient pas à cet utilisateur.');
                    }
                }
            ],
        ];
    }

    /**
     * Retrieve the validated payment UUID.
     *
     * @return string
     */
    public function validatedUuid(): string
    {
        return $this->validated()['payment_uuid'];
    }
}
