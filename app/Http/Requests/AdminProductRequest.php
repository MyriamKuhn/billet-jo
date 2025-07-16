<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * FormRequest to validate admin-level product queries and updates.
 *
 * Ensures that only administrators may use this endpoint,
 * and that all provided query parameters conform to expected formats.
 */
class AdminProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if authenticated user has admin role.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Define validation rules for each possible filter or pagination parameter.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'date'     => 'sometimes|date_format:Y-m-d',
            'places'   => 'sometimes|integer|min:1',
            // Sort field must be one of the allowed columns
            'sort_by'  => [
                'sometimes',
                Rule::in(['name','price','product_details->date']),
            ],
            // Order direction must be 'asc' or 'desc'
            'order'    => [
                'sometimes',
                Rule::in(['asc','desc']),
            ],
            // Pagination settings
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page'     => 'sometimes|integer|min:1',
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = array_keys($this->rules());
        $extra   = array_diff(array_keys($this->all()), $allowed);

        if (! empty($extra)) {
            abort(400);
        }
    }
}
