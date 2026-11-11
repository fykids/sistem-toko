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
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->string('seller_name')->nullable();
            $table->string('seller_nik', 16)->nullable();
            $table->string('seller_phone', 30)->nullable();
            $table->text('seller_address')->nullable();
            $table->string('broker_name')->nullable();
        });

        Schema::create('vehicle_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('car_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->boolean('is_cover')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
            $table->index(['car_transaction_id', 'position']);
        });

        DB::table('car_transactions')
            ->whereNotNull('vehicle_photo_path')
            ->orderBy('id')
            ->chunkById(500, function ($transactions): void {
                $now = now();
                $photos = $transactions->map(static fn (object $transaction): array => [
                    'car_transaction_id' => $transaction->id,
                    'path' => $transaction->vehicle_photo_path,
                    'is_cover' => true,
                    'position' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                DB::table('vehicle_photos')->insert($photos);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vehicle_photos');

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'seller_name',
                'seller_nik',
                'seller_phone',
                'seller_address',
                'broker_name',
            ]);
        });
    }
};
