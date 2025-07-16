<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Form request to handle user registration submissions.
 *
 * Includes validation for:
 * - Personal details (firstname, lastname)
 * - Unique email address
 * - Strong password requirements (min 15 chars, mixed case, numbers, symbols)
 * - CAPTCHA token verification
 * - Terms acceptance checkbox
 *
 * @OA\Schema(
 *   schema="RegisterUser",
 *   required={"firstname","lastname","email","password","password_confirmation","captcha_token"},
 *   @OA\Property(property="firstname",           type="string", example="Jean"),
 *   @OA\Property(property="lastname",            type="string", example="Dupont"),
 *   @OA\Property(property="email",               type="string", format="email", example="jean.dupont@example.com"),
 *   @OA\Property(property="password",            type="string", format="password", example="Str0ngP@ssw0rd!"),
 *   @OA\Property(property="password_confirmation", type="string", format="password", example="Str0ngP@ssw0rd!"),
 *   @OA\Property(property="captcha_token",       type="string", example="03AGdBq24…"),
 *   @OA\Property(property="accept_terms",       type="boolean", example=true),
 * )
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool  Always true: anyone may attempt to register.
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
            // First and last names: required, string, max length 100
            'firstname'     => 'required|string|max:100',
            'lastname'      => 'required|string|max:100',
            // Email: required, valid email format, unique in users table
            'email'         => 'required|email|max:100|unique:users,email',
            // Password: required, must match confirmation, and follow strong rules
            'password'      => ['required','confirmed',
                                Password::min(15)
                                    ->mixedCase()
                                    ->letters()
                                    ->numbers()
                                    ->symbols()
                                ],
            // CAPTCHA token for bot protection
            'captcha_token' => 'required|string',
            // Acceptance of terms must be explicitly true or false
            'accept_terms' => 'required|boolean',
        ];
    }
}
