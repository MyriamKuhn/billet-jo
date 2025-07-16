<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request to handle user password update submissions.
 *
 * Validates the current password and ensures the new password
 * meets strong security requirements and is confirmed.
 */
class UpdatePasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True: any authenticated user may change their password.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The user's current password must be provided
            'current_password' => 'required|string',
            // New password: required, must match confirmation, and follow strong rules
            'password' => [
                'required',
                'confirmed',
                Password::min(15)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
            // Confirmation field must be present and a string
            'password_confirmation'  => 'required|string',
        ];
    }
}
