<?php
// app/Http/Requests/User/UpdateUserNameRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNameRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => 'required|string|max:255',
            'lastname'  => 'required|string|max:255',
        ];
    }
}

