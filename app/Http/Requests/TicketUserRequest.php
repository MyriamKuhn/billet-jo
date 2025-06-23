<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketUserRequest extends FormRequest
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
            'status'    => ['sometimes', 'string', 'in:issued,used,refunded,cancelled'],
            'per_page'  => ['sometimes','integer','min:1','max:100'],
            'page'      => ['sometimes','integer','min:1'],
            'event_date_from'  => ['sometimes','date'],
            'event_date_to'    => ['sometimes','date','after_or_equal:event_date_from'],
        ];
    }

    /**
     * Return the validated filters as an array.
     *
     * @return array
     */
    public function validatedFilters(): array
    {
        return collect($this->validated())
            ->only(['status','per_page','page','event_date_from','event_date_to'])
            ->toArray();
    }
}
