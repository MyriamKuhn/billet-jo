<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for user login.
 *
 * Validates email, password, optional "remember me" flag,
 * and an optional two-factor authentication code.
 */
class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True since any user can attempt to log in.
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
            // The user's email must be provided and be a valid email address
            'email'      => 'required|email',
            // The user's password must be provided as a string
            'password'   => 'required|string',
            // "remember" is optional and should be a boolean if present
            'remember'   => 'boolean',
            // 2FA code is optional and must be a string if provided
            'twofa_code' => 'nullable|string',
        ];
    }
}
