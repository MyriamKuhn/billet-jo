<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate admin-level payment listing filters.
 *
 * Ensures only administrators may request the payments list,
 * and that all provided query parameters conform to expected formats.
 */
class PaymentIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the user is authenticated and has the admin role.
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
            // Global search term for payment fields
            'q'              => 'sometimes|string',
            // Filter by payment status
            'status'         => 'sometimes|in:pending,paid,failed,refunded',
            // Filter by payment method (provider)
            'payment_method' => 'sometimes|in:paypal,stripe,free',
            // Filter by user ID, must exist in users table
            'user_id'        => 'sometimes|integer|exists:users,id',
            // Date range filters
            'date_from'      => 'sometimes|date',
            'date_to'        => 'sometimes|date|after_or_equal:date_from',
            // Amount range filters
            'amount_min'     => 'sometimes|numeric|min:0',
            'amount_max'     => 'sometimes|numeric|min:0',
            // Sorting options
            'sort_by'        => 'sometimes|in:uuid,amount,paid_at,refunded_at,created_at',
            'sort_order'     => 'sometimes|in:asc,desc',
            // Pagination limit
            'per_page'       => 'sometimes|integer|min:1|max:100',
        ];
    }

    /**
     * Retrieve validated filters for use in the controller.
     *
     * @return array<string, mixed>  Only the filters present in the request.
     */
    public function validatedFilters(): array
    {
        // Return only the filters that are present in the request
        return collect($this->validated())
            ->only([
                'q',
                'status',
                'payment_method',
                'user_id',
                'date_from',
                'date_to',
                'amount_min',
                'amount_max',
            ])->toArray();
    }
}
