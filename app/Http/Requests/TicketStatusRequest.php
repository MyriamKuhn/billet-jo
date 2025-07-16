<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\TicketStatus;

/**
 * Form request to validate and authorize updates to a ticket's status (admin only).
 *
 * Ensures that only authenticated administrators may change a ticket's status,
 * and that the new status value is one of the allowed enum cases.
 */
class TicketStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Only authenticated admins can update ticket status.
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
     * - status: required and must be one of the values defined in TicketStatus enum.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                // Allow only values from the TicketStatus enum
                Rule::in(array_map(fn(TicketStatus $s) => $s->value, TicketStatus::cases())),
            ],
        ];
    }

    /**
     * Retrieve the validated status value.
     *
     * @return string  The new ticket status.
     */
    public function validatedStatus(): string
    {
        return $this->validated()['status'];
    }
}
