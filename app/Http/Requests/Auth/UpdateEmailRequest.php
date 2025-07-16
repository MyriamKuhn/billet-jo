<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to handle user email update submissions.
 *
 * Validates that the new email is provided, formatted correctly,
 * and is unique in the users table.
 */
class UpdateEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  True: any authenticated user may request an email change.
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
            // New email must be provided, valid, and unique among users
            'email' => 'required|email|unique:users,email',
        ];
    }
}
