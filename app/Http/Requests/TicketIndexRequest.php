<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TicketStatus;

class TicketIndexRequest extends FormRequest
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
            'q'                 => 'sometimes|string',
            'status'            => 'sometimes|in:issued,used,refunded,cancelled',
            'user_id'           => 'sometimes|integer|exists:users,id',
            'user_email'        => 'sometimes|email|exists:users,email',
            'product_id'     => 'sometimes|integer|exists:products,id',
            'payment_uuid'   => 'sometimes|string|exists:payments,uuid',
            'per_page'          => 'sometimes|integer|min:1|max:100',
            'page'           => 'sometimes|integer|min:1',
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
     * Return only the validated filter parameters.
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
