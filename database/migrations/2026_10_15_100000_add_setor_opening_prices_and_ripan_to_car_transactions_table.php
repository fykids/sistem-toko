<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->decimal('setor_price', 15, 2)->default(0);
            $table->decimal('opening_price', 15, 2)->default(0);
            $table->decimal('leasing_ripan', 15, 2)->default(0);
            $table->decimal('insurance_ripan', 15, 2)->default(0);
            $table->decimal('booster_ripan', 15, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'setor_price',
                'opening_price',
                'leasing_ripan',
                'insurance_ripan',
                'booster_ripan',
            ]);
        });
    }
};
