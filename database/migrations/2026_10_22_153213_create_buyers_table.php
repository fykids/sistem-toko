<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_transaction_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_nik', 16)->nullable();
            $table->string('buyer_phone', 30)->nullable();
            $table->text('buyer_address')->nullable();
            $table->decimal('sell_price_deal', 15, 2);
            $table->string('payment_method', 50);
            $table->date('sell_date')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
