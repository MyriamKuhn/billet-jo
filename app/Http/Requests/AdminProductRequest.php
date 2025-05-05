<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'name'     => 'sometimes|string|max:255',
            'category' => 'sometimes|string|max:255',
            'location' => 'sometimes|string|max:255',
            'date'     => 'sometimes|date_format:Y-m-d',
            'places'   => 'sometimes|integer|min:1',
            'sort_by'  => [
                'sometimes',
                Rule::in(['name','price','product_details->date']),
            ],
            'order'    => [
                'sometimes',
                Rule::in(['asc','desc']),
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
