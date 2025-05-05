<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    /**
     * Everyone can list products; adjust if needed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules for query parameters.
     */
    public function rules(): array
    {
        return [
            'name'     => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'date'     => 'sometimes|date_format:Y-m-d',
            'places'   => 'sometimes|integer|min:1',
            'sort_by'  => [
                'sometimes',
                Rule::in(['name', 'price', 'product_details->date']),
            ],
            'order'    => [
                'sometimes',
                Rule::in(['asc', 'desc']),
            ],
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
