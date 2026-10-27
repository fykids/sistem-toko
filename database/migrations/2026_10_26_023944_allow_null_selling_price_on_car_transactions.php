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
        Schema::table('car_transactions', function (Blueprint $table): void {
            $table->decimal('selling_price', 15, 2)->default(0)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('car_transactions')
            ->whereNull('selling_price')
            ->update(['selling_price' => 0]);

        Schema::table('car_transactions', function (Blueprint $table): void {
            $table->decimal('selling_price', 15, 2)->default(0)->nullable(false)->change();
        });
    }
};
