<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request to validate password reset submissions.
 *
 * Ensures the reset token is present, the email exists, and the new password
 * meets strong security requirements and is confirmed.
 */
class ResetPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True: any user with a valid token may reset password.
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
            // The reset token sent via email link must be provided
            'token' => 'required|string',
            // Email must be valid and correspond to an existing user
            'email' => 'required|email|exists:users,email',
            // New password: required, must match confirmation, and meet strength rules
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
            'password_confirmation' => 'required|string',
        ];
    }
}
