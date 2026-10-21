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
                'email' => 'master@toko.com',
                'password' => 'password',
                'role' => 'master',
            ],
            [
                'name' => 'Admin',
                'email' => 'admin@toko.com',
                'password' => 'password',
                'role' => 'admin',
            ],
        ] as $account) {
            $user = User::query()->firstOrNew(['email' => $account['email']]);

            $user->forceFill([
                'name' => $account['name'],
                'password' => Hash::make($account['password']),
                'role' => $account['role'],
            ])->save();
        }
    }
}
