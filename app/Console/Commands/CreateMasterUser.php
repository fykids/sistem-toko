<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('app:create-master-user')]
#[Description('Create the initial Master account using operator-supplied credentials')]
class CreateMasterUser extends Command
{
    public function handle(UserService $userService): int
    {
        if (User::query()->where('role', 'master')->exists()) {
            $this->error('A Master account already exists.');

            return self::FAILURE;
        }

        $name = $this->ask('Name');
        $username = $this->ask('Username');
        $password = $this->secret('Password (minimum 6 characters)');
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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $master = $userService->createInitialMaster([
            'name' => $name,
            'username' => $username,
            'password' => $password,
        ]);

        if ($master === null) {
            $this->error('A Master account already exists.');

            return self::FAILURE;
        }

        $this->info('Master account created. Keep the credentials secure.');

        return self::SUCCESS;
    }
}
