<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * Form request to validate data when creating a new employee account.
 *
 * Ensures required personal information and enforces strong password rules.
 */
class StoreEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  Always true; endpoint access should be restricted by middleware.
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
            // Required first name (string up to 255 chars)
            'firstname'             => 'required|string|max:255',
            // Required last name (string up to 255 chars)
            'lastname'              => 'required|string|max:255',
            // Required email, must be unique in users table
            'email'                 => 'required|email|unique:users,email',
 ,           // Password: required, must match confirmation, and follow strong policy:
 ,           // at least 15 chars, mixed case, letters, numbers & symbols
            'password'              => [
                'required',
                'confirmed',
                PasswordRule::min(15)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ];
    }
}
