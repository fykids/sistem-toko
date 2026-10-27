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
            $table->index('buy_date', 'car_transactions_purchase_date_index');
            $table->index(['created_at', 'id'], 'car_transactions_dashboard_sort_index');
            $table->index(['sell_date', 'created_at', 'id'], 'car_transactions_status_sort_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropIndex('car_transactions_purchase_date_index');
            $table->dropIndex('car_transactions_dashboard_sort_index');
            $table->dropIndex('car_transactions_status_sort_index');
        });
    }
};
