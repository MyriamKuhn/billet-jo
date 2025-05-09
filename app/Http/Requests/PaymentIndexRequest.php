<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentIndexRequest extends FormRequest
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
            'q'              => 'sometimes|string',
            'status'         => 'sometimes|in:pending,paid,failed,refunded',
            'payment_method' => 'sometimes|in:paypal,stripe',
            'user_id'        => 'sometimes|integer|exists:users,id',
            'date_from'      => 'sometimes|date',
            'date_to'        => 'sometimes|date|after_or_equal:date_from',
            'amount_min'     => 'sometimes|numeric|min:0',
            'amount_max'     => 'sometimes|numeric|min:0',
            'sort_by'        => 'sometimes|in:uuid,amount,paid_at,refunded_at,created_at',
            'sort_order'     => 'sometimes|in:asc,desc',
            'per_page'       => 'sometimes|integer|min:1|max:100',
        ];
    }

    /**
     * Validate the filters and return them as an array.
     *
     * @return array
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
