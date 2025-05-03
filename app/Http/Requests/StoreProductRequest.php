<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\UserRole;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'sale' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'product_details' => ['required', 'array'],
            'product_details.places' => ['required', 'integer', 'min:1'],
            'product_details.description' => ['required', 'string'],
            'product_details.date' => ['required', 'date_format:Y-m-d'],
            'product_details.time' => ['required', 'string'],
            'product_details.location' => ['required', 'string'],
            'product_details.category' => ['required', 'string'],
            'product_details.image' => ['required', 'string'],
        ];
    }
}
