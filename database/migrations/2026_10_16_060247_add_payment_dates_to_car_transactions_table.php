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
            $table->date('down_payment_date')->nullable();
            $table->date('settlement_date')->nullable();
            $table->date('leasing_disbursement_date')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'down_payment_date',
                'settlement_date',
                'leasing_disbursement_date',
            ]);
        });
    }
};
