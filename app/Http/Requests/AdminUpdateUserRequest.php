<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminUpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
        $userId = $this->route('user')->id;

        return [
            'is_active'      => 'nullable|boolean',
            'twofa_enabled'  => 'nullable|boolean',
            'firstname'      => 'nullable|string|max:255',
            'lastname'       => 'nullable|string|max:255',
            'email'          => [
                'nullable',
                'email',
                Rule::unique('users','email')->ignore($userId),
            ],
            'role'           => 'nullable|in:admin,employee,user',
            'verify_email'   => 'nullable|boolean',
        ];
    }
}
