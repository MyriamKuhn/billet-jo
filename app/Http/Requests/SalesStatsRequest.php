<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SalesStatsRequest extends FormRequest
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
            'q'          => ['sometimes','string'],                        // recherche sur nom produit
            'sort_by'    => ['sometimes','in:sales_count'],   // tri
            'sort_order' => ['sometimes','in:asc,desc'],
            'per_page'   => ['sometimes','integer','min:1','max:100'],
            'page'       => ['sometimes','integer','min:1'],
        ];
    }

    /**
     * Get the validation attributes for the request.
     *
     * @return array<string, string>
     */
    public function validatedFilters(): array
    {
        return collect($this->validated())
            ->only(['q','sort_by','sort_order','per_page','page'])
            ->toArray();
    }
}
