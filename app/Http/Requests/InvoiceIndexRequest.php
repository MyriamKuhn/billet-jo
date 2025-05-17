<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InvoiceIndexRequest extends FormRequest
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
            'status'     => ['sometimes', 'string', 'in:pending,paid,failed,refunded'],
            'date_from'  => ['sometimes', 'date_format:Y-m-d'],
            'date_to'    => ['sometimes', 'date_format:Y-m-d'],
            'sort_by'    => ['sometimes', 'string', 'in:uuid,amount,created_at'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page'   => ['sometimes', 'integer', 'min:1'],
            'page'       => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation()
    {
        // Ensure dates are trimmed
        if ($this->has('date_from')) {
            $this->merge(['date_from' => trim($this->query('date_from'))]);
        }
        if ($this->has('date_to')) {
            $this->merge(['date_to' => trim($this->query('date_to'))]);
        }
    }

    /**
     * Get filters array for the controller.
     *
     * @return array<string, mixed>
     */
    public function validatedFilters(): array
    {
        return array_filter([
            'status'     => $this->query('status'),
            'date_from'  => $this->query('date_from'),
            'date_to'    => $this->query('date_to'),
        ], fn($value) => !is_null($value));
    }

    /**
     * Get pagination and sort parameters, with defaults.
     *
     * @return array<string, mixed>
     */
    public function paginationAndSort(): array
    {
        return [
            'sort_by'    => $this->query('sort_by', 'created_at'),
            'sort_order' => $this->query('sort_order', 'desc'),
            'per_page'   => (int) $this->query('per_page', 15),
            'page'       => (int) $this->query('page', 1),
        ];
    }
}
