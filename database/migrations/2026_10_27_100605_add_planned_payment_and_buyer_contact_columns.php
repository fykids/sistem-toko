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
            $table->date('planned_payment_date')->nullable();
        });

        Schema::table('sale_notes', function (Blueprint $table) {
            $table->string('buyer_nik', 16)->nullable();
            $table->string('buyer_phone', 30)->nullable();
            $table->text('buyer_address')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_notes', function (Blueprint $table) {
            $table->dropColumn(['buyer_nik', 'buyer_phone', 'buyer_address']);
        });

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn('planned_payment_date');
        });
    }
};
