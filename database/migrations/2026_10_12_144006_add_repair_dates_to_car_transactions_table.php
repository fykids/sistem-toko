<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->date('battery_repair_date')->nullable();
            $table->date('undercarriage_repair_date')->nullable();
            $table->date('bumper_paint_repair_date')->nullable();
            $table->date('oil_change_repair_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'battery_repair_date',
                'undercarriage_repair_date',
                'bumper_paint_repair_date',
                'oil_change_repair_date',
            ]);
        });
    }
};
