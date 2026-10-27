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
        Schema::create('sale_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_transaction_id')
                ->unique()
                ->constrained('car_transactions')
                ->cascadeOnDelete();
            $table->string('buyer_name');
            $table->string('buyer_nik')->nullable();
            $table->string('buyer_phone')->nullable();
            $table->text('buyer_address')->nullable();
            $table->decimal('deal_price', 15, 2);
            $table->string('payment_method')->default('cash');
            $table->date('sell_date')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_notes');
    }
};
