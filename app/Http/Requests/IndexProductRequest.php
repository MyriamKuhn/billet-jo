<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request to validate product listing queries for both guest and authenticated users.
 *
 * Allows filtering by various fields, sorting, and pagination while
 * rejecting unexpected parameters.
 */
class IndexProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  Always true: anyone may list products.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional filters
            'name'     => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'date'     => 'sometimes|date_format:Y-m-d',
            'places'   => 'sometimes|integer|min:1',
            // Sorting options
            'sort_by'  => [
                'sometimes',
                Rule::in(['name', 'price', 'product_details->date']),
            ],
            'order'    => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],
            // Pagination settings
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page'     => 'sometimes|integer|min:1',
        ];
    }

    /**
     * Abort request if any unexpected parameters are present.
     *
     * Ensures no extra query parameters beyond those defined in rules().
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $allowed = array_keys($this->rules());
        $extra   = array_diff(array_keys($this->all()), $allowed);

        if (! empty($extra)) {
            abort(400);
        }
    }
}
