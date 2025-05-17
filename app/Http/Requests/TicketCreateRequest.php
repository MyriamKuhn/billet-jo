<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TicketCreateRequest extends FormRequest
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
        return [
            'user_id'    => ['required','integer','exists:users,id'],
            'product_id' => ['required','integer','exists:products,id'],
            'quantity'   => ['required','integer','min:1'],
            'locale'     => ['sometimes','string','in:en,fr,de'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function validatedData(): array
    {
        $data = $this->validated();
        $data['locale'] = $data['locale'] ?? explode(',', $this->header('Accept-Language','en'))[0];
        return $data;
    }
}
