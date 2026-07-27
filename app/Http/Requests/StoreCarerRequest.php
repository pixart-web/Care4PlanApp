<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canOperate() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'age' => ['nullable', 'integer', 'min:16', 'max:100'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'color_hex' => ['nullable', 'string', 'size:7', Rule::unique('carers', 'color_hex')],
        ];
    }
}
