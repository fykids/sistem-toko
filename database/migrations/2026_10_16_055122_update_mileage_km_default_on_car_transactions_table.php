<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('car_transactions')
            ->whereNull('mileage_km')
            ->update(['mileage_km' => 0]);

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->unsignedInteger('mileage_km')->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->unsignedInteger('mileage_km')->nullable()->default(null)->change();
        });
    }
};
