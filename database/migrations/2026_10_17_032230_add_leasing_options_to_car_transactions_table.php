<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->json('leasing_options')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn('leasing_options');
        });
    }
};
