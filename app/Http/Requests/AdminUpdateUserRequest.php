<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request to validate admin-driven user updates.
 *
 * Ensures only administrators may modify user properties
 * and that all provided fields adhere to expected formats.
 */
class AdminUpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True if the authenticated user has an admin role.
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
        // Retrieve the user ID from the route for unique email rule
        $userId = $this->route('user')->id;

        return [
            // Toggle user active status (optional boolean)
            'is_active'      => 'nullable|boolean',
            // Toggle two-factor authentication (optional boolean)
            'twofa_enabled'  => 'nullable|boolean',
            // Personal names (optional strings)
            'firstname'      => 'nullable|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            // Email: optional, valid email format, unique except for current user
            'email'          => [
                'nullable',
                'email',
                Rule::unique('users','email')->ignore($userId),
            ],
            // Role assignment: must be one of the defined roles
            'role'           => 'nullable|in:admin,employee,user',
            // Flag to trigger email verification (optional boolean)
            'verify_email'   => 'nullable|boolean',
        ];
    }
}
