<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request to validate updating the authenticated user's firstname and lastname.
 *
 * This request ensures:
 * - The user is authenticated (authorization handled elsewhere).
 * - The firstname and lastname fields are present, are strings, and do not exceed 255 characters.
 */
class UpdateUserNameRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * All authenticated users may update their own name.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Authorization (e.g. ensuring the user is updating their own profile)
        // is managed by middleware or controller logic; allow by default here.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * - firstname: required, string, max length 255.
     * - lastname:  required, string, max length 255.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
        ];
    }
}

