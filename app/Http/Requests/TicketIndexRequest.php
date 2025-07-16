<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate filtering and pagination parameters for listing all tickets (admin only).
 *
 * Ensures that only admins may access the endpoint, and that any provided filters,
 * date ranges, and pagination values conform to expected formats and relationships.
 */
class TicketIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated administrators can list all tickets.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Supports:
     * - Global search by token/product/category.
     * - Filtering by status, user, product, or payment.
     * - Date-range filters for created, updated, used, refunded, or cancelled timestamps.
     * - Pagination parameters.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Optional global search term (ticket token, product name, etc.)
            'q'                 => 'sometimes|string',
            // Ticket status filter
            'status'            => 'sometimes|in:issued,used,refunded,cancelled',
            // Filter by user ID or email
            'user_id'           => 'sometimes|integer|exists:users,id',
            'user_email'        => 'sometimes|email|exists:users,email',
            // Filter by product or payment
            'product_id'     => 'sometimes|integer|exists:products,id',
            'payment_uuid'   => 'sometimes|string|exists:payments,uuid',
            // Pagination
            'per_page'          => 'sometimes|integer|min:1|max:100',
            'page'           => 'sometimes|integer|min:1',
            // Date range filters
            'created_from'      => 'sometimes|date',
            'created_to'        => 'sometimes|date|after_or_equal:created_from',
            'updated_from'      => 'sometimes|date',
            'updated_to'        => 'sometimes|date|after_or_equal:updated_from',
            'used_from'         => 'sometimes|date',
            'used_to'           => 'sometimes|date|after_or_equal:used_from',
            'refunded_from'     => 'sometimes|date',
            'refunded_to'       => 'sometimes|date|after_or_equal:refunded_from',
            'cancelled_from'    => 'sometimes|date',
            'cancelled_to'      => 'sometimes|date|after_or_equal:cancelled_from',
        ];
    }

    /**
     * Retrieve only the validated filters for use in the controller.
     *
     * @return array<string, mixed>  Subset of validated inputs corresponding to filters.
     */
    public function validatedFilters(): array
    {
        return collect($this->validated())
            ->only([
                'q',
                'status',
                'user_id',
                'user_email',
                'product_id',
                'payment_uuid',
                'per_page',
                'page',
                'created_from', 'created_to',
                'updated_from', 'updated_to',
                'used_from',    'used_to',
                'refunded_from','refunded_to',
                'cancelled_from','cancelled_to',
            ])
            ->toArray();
    }
}
