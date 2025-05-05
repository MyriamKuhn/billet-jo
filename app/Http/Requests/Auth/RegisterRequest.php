<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * @OA\Schema(
 *   schema="RegisterUser",
 *   required={"firstname","lastname","email","password","password_confirmation","captcha_token"},
 *   @OA\Property(property="firstname",           type="string", example="Jean"),
 *   @OA\Property(property="lastname",            type="string", example="Dupont"),
 *   @OA\Property(property="email",               type="string", format="email", example="jean.dupont@example.com"),
 *   @OA\Property(property="password",            type="string", format="password", example="Str0ngP@ssw0rd!"),
 *   @OA\Property(property="password_confirmation", type="string", format="password", example="Str0ngP@ssw0rd!"),
 *   @OA\Property(property="captcha_token",       type="string", example="03AGdBq24…"),
 * )
 */
class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
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
            'firstname'     => 'required|string|max:100',
            'lastname'      => 'required|string|max:100',
            'email'         => 'required|email|max:100|unique:users,email',
            'password'      => ['required','confirmed',
                                Password::min(15)
                                    ->mixedCase()
                                    ->letters()
                                    ->numbers()
                                    ->symbols()],
            'captcha_token' => 'required|string',
        ];
    }
}
