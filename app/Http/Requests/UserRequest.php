<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'role' => ['required', Rule::in(['ADMIN', 'EDITOR', 'CARER'])],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'carer_id' => ['nullable', 'exists:carers,id'],
        ];
    }
}
