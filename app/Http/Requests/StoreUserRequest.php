<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('create', User::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9._-]+$/',
                'max:255',
                Rule::unique(User::class, 'username'),
            ],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['master', 'admin', 'marketing'])],
        ];
    }
}
