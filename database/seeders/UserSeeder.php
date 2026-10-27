<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Dummy login accounts may only be seeded in local or testing environments.');
        }

        foreach ([
            [
                'name' => 'Master',
                'username' => 'master',
                'password' => 'password',
                'role' => 'master',
            ],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'password' => 'password',
                'role' => 'admin',
            ],
        ] as $account) {
            $user = User::query()->firstOrNew(['username' => $account['username']]);

            $user->forceFill([
                'name' => $account['name'],
                'password' => Hash::make($account['password']),
                'role' => $account['role'],
            ])->save();
        }
    }
}
