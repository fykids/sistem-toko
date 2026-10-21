<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $managedUser = $this->route('user');

        return $user instanceof User
            && $managedUser instanceof User
            && $user->can('update', $managedUser);
    }

    public function rules(): array
    {
        $managedUser = $this->route('user');

        if (! $managedUser instanceof User) {
            return [];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($managedUser),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(['master', 'admin'])],
        ];
    }

    public function after(): array
    {
        $managedUser = $this->route('user');

        if (! $managedUser instanceof User) {
            return [];
        }

        return [
            function (Validator $validator) use ($managedUser): void {
                if (
                    $validator->errors()->has('role')
                    || $managedUser->role !== 'master'
                    || $this->input('role') !== 'admin'
                ) {
                    return;
                }

                if (User::query()->where('role', 'master')->count() <= 1) {
                    $validator->errors()->add('role', 'Sistem harus mempertahankan minimal satu akun Master.');
                }
            },
        ];
    }
}
