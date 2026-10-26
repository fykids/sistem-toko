<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['master', 'admin', 'marketing', 'viewer'])
                ->default('admin')
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('users')->whereNotIn('role', ['master', 'admin'])->exists()) {
            throw new RuntimeException(
                'Cannot roll back the role migration while users have roles outside master or admin.',
            );
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['master', 'admin'])
                ->default('admin')
                ->change();
        });
    }
};
