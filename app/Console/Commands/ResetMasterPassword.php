<?php

namespace App\Console\Commands;

use App\Services\UserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

#[Signature('app:reset-master-password')]
#[Description('Set a new password for the first Master account')]
class ResetMasterPassword extends Command
{
    public function handle(UserService $userService): int
    {
        if (! $userService->hasMasterAccount()) {
            $this->error('A Master account does not exist.');

            return self::FAILURE;
        }

        $password = $this->secret('New password (minimum 12 characters)');
        $passwordConfirmation = $this->secret('Confirm new password');
        $validator = Validator::make([
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
        ], [
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $master = $userService->resetMasterPassword($password);

        if ($master === null) {
            $this->error('A Master account does not exist.');

            return self::FAILURE;
        }

        $this->info('Password akun Master berhasil direset.');

        return self::SUCCESS;
    }
}
