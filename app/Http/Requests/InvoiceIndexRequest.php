<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate invoice listing parameters for authenticated users.
 *
 * Ensures the user is authenticated and all provided query parameters
 * for filtering, sorting, and pagination conform to expected formats.
 */
class InvoiceIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the user is authenticated.
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
            // Filter by invoice status: pending, paid, failed, or refunded
            'status'     => ['sometimes', 'string', 'in:pending,paid,failed,refunded'],
            // Date range filters in YYYY-MM-DD format
            'date_from'  => ['sometimes', 'date_format:Y-m-d'],
            'date_to'    => ['sometimes', 'date_format:Y-m-d'],
            // Sorting options
            'sort_by'    => ['sometimes', 'string', 'in:uuid,amount,created_at'],
            'sort_order' => ['sometimes', 'string', 'in:asc,desc'],
            // Pagination parameters
            'per_page'   => ['sometimes', 'integer', 'min:1'],
            'page'       => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Prepare the data for validation.
     *
     * Trim whitespace from date fields if they are present.
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
     * Retrieve only the filter parameters that have been provided.
     *
     * @return array<string, mixed>  Associative array of non-null filters.
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
     * Retrieve pagination and sorting options with default values.
     *
     * @return array<string, mixed>  Array containing sort_by, sort_order, per_page, and page.
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
