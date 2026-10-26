<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

#[Signature('app:create-master-user')]
#[Description('Create the initial Master account using operator-supplied credentials')]
class CreateMasterUser extends Command
{
    public function handle(): int
    {
        if (User::query()->where('role', 'master')->exists()) {
            $this->error('A Master account already exists.');

            return self::FAILURE;
        }

        $name = $this->ask('Name');
        $username = $this->ask('Username');
        $password = $this->secret('Password (minimum 12 characters)');
        $passwordConfirmation = $this->secret('Confirm password');

        $validator = Validator::make([
            'name' => $name,
            'username' => $username,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'regex:/^[A-Za-z0-9._-]+$/',
                'max:255',
                'unique:users,username',
            ],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $created = DB::transaction(function () use ($name, $username, $password): bool {
            if (User::query()->where('role', 'master')->exists()) {
                return false;
            }

            $user = new User;
            $user->forceFill([
                'name' => $name,
                'username' => $username,
                'password' => Hash::make($password),
                'role' => 'master',
            ])->save();

            return true;
        });

        if (! $created) {
            $this->error('A Master account already exists.');

            return self::FAILURE;
        }

        $this->info('Master account created. Keep the credentials secure.');

        return self::SUCCESS;
    }
}
