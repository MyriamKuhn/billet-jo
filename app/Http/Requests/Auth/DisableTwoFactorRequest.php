<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request class to validate the disabling of two-factor authentication.
 *
 * Ensures the provided 2FA code is present and correctly formatted.
 */
class DisableTwoFactorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // All authenticated users may attempt to disable 2FA
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
            // The current 2FA code must be provided as a string
            'twofa_code' => ['required', 'string'],
        ];
    }
}
