<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketStoreRequest extends FormRequest
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
            'payment_uuid' => [
                'required',
                'uuid',
                // Check if the payment belongs to the authenticated user
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
     * Get the validated data.
     *
     * @return array
     */
    public function validatedUuid(): string
    {
        return $this->validated()['payment_uuid'];
    }
}
