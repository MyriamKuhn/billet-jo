<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate data for generating free tickets (admin only).
 *
 * Ensures that only administrators may create tickets for an existing user and product,
 * enforces quantity minimums, and applies a default locale if none is provided.
 */
class TicketCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated admins may generate free tickets.
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
     * - user_id: must refer to an existing user.
     * - product_id: must refer to an existing product.
     * - quantity: at least 1 ticket.
     * - locale: optional, defaults based on Accept-Language header (en, fr, de).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // ID of the existing user to receive tickets
            'user_id'    => ['required','integer','exists:users,id'],
            // ID of the product/event for which tickets are generated
            'product_id' => ['required','integer','exists:products,id'],
            // Number of tickets to generate; must be at least one
            'quantity'   => ['required','integer','min:1'],
            // Optional locale for generated tickets/invoice (en, fr, de)
            'locale'     => ['sometimes','string','in:en,fr,de'],
        ];
    }

    /**
     * Retrieve the validated data and apply defaults.
     *
     * - Merges the locale: if not provided, uses the first value
     *   from the Accept-Language header (default 'en').
     *
     * @return array<string, mixed>
     */
    public function validatedData(): array
    {
        $data = $this->validated();
        $data['locale'] = $data['locale'] ?? explode(',', $this->header('Accept-Language','en'))[0];
        return $data;
    }
}
