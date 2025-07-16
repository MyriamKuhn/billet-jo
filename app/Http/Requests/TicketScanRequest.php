<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to authorize scanning of ticket QR codes by employees.
 *
 * Ensures that only authenticated users with the employee role
 * can access the ticket scanning endpoints. No additional request
 * data is validated, since the ticket token is provided via the URL.
 */
class TicketScanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the authenticated user is an employee.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->role->isEmployee();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * No extra form or query parameters are required for scanning;
     * the ticket token is obtained from the route parameter.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // No request body validation needed
        ];
    }
}
