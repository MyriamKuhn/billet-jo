<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate filtering parameters when listing tickets for the
 * currently authenticated user.
 *
 * Allows optional filtering by ticket status and event date range,
 * as well as pagination controls.
 */
class TicketUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Any authenticated user may view their own tickets.
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
     * - status: optional ticket status filter.
     * - per_page/page: optional pagination parameters.
     * - event_date_from/to: optional event date range filters.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional filter by ticket status
            'status'    => ['sometimes', 'string', 'in:issued,used,refunded,cancelled'],
            // Pagination parameters
            'per_page'  => ['sometimes','integer','min:1','max:100'],
            'page'      => ['sometimes','integer','min:1'],
            // Optional event date range filters
            'event_date_from'  => ['sometimes','date'],
            'event_date_to'    => ['sometimes','date','after_or_equal:event_date_from'],
        ];
    }

    /**
     * Retrieve only the validated filter parameters for the controller.
     *
     * @return array<string, mixed>
     */
    public function validatedFilters(): array
    {
        return collect($this->validated())
            ->only(['status','per_page','page','event_date_from','event_date_to'])
            ->toArray();
    }
}
