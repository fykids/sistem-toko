<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable();
        });

        DB::table('users')
            ->select(['id', 'email as legacy_email'])
            ->orderBy('id')
            ->chunkById(100, function ($users): void {
                foreach ($users as $user) {
                    $username = Str::lower(
                        Str::ascii(Str::before((string) $user->legacy_email, '@'))
                    );
                    $username = trim((string) preg_replace('/[^a-z0-9]+/', '_', $username), '_');
                    $username = substr($username !== '' ? $username : 'user_'.$user->id, 0, 255);
                    $baseUsername = $username;
                    $suffix = 1;

                    while (DB::table('users')->where('username', $username)->exists()) {
                        $username = substr($baseUsername, 0, 254 - strlen((string) $suffix))
                            .'_'.$suffix++;
                    }

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update(['username' => $username]);
                }
            });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username')->nullable(false)->change();
            $table->unique('username');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_unique');
            $table->dropColumn(['email', 'email_verified_at']);
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException(
            'User email addresses are not recoverable after this migration; restore a database backup instead.'
        );
    }
};
