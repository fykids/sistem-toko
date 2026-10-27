<?php

namespace App\Console\Commands;

use App\Services\UserService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reset-master-password')]
#[Description('Reset the first Master account password to 123456')]
class ResetMasterPassword extends Command
{
    public function handle(UserService $userService): int
    {
        $master = $userService->resetMasterPassword();

        if ($master === null) {
            $this->error('A Master account does not exist.');

            return self::FAILURE;
        }

        $this->info('Password akun Master berhasil direset menjadi: 123456');

        return self::SUCCESS;
    }
}
