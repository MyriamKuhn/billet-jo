<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate ticket sales statistics queries for administrators.
 *
 * Ensures that only admin users can access sales stats,
 * and that any provided query parameters (search, sorting, pagination)
 * conform to expected formats.
 */
class SalesStatsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the authenticated user has the admin role.
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
        return [
            // Optional global search by product name
            'q'          => ['sometimes','string'],
            // Optional sorting field; currently only 'sales_count' is supported
            'sort_by'    => ['sometimes','in:sales_count'],
            // Optional sort direction ('asc' or 'desc')
            'sort_order' => ['sometimes','in:asc,desc'],
            // Pagination: items per page (1–100)
            'per_page'   => ['sometimes','integer','min:1','max:100'],
            // Pagination: page number (starting from 1)
            'page'       => ['sometimes','integer','min:1'],
        ];
    }

    /**
     * Retrieve only the validated filters for use in the controller.
     *
     * @return array<string, mixed>  Key/value pairs of the present filters.
     */
    public function validatedFilters(): array
    {
        return collect($this->validated())
            ->only(['q','sort_by','sort_order','per_page','page'])
            ->toArray();
    }
}
