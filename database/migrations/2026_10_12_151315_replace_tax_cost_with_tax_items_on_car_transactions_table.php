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
            $table->json('tax_items')->nullable();
        });

        DB::table('car_transactions')
            ->select(['id', 'tax_cost'])
            ->where('tax_cost', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    DB::table('car_transactions')->where('id', $transaction->id)->update([
                        'tax_items' => json_encode([[
                            'name' => 'Pajak',
                            'date' => null,
                            'cost' => (float) $transaction->tax_cost,
                            'payment_status' => 'lainnya',
                            'payment_note' => 'Status pembayaran sebelum migrasi tidak tercatat',
                        ]], JSON_THROW_ON_ERROR),
                    ]);
                }
            });

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn('tax_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->decimal('tax_cost', 15, 2)->default(0);
        });

        DB::table('car_transactions')
            ->select(['id', 'tax_items'])
            ->whereNotNull('tax_items')
            ->orderBy('id')
            ->chunkById(100, function ($transactions): void {
                foreach ($transactions as $transaction) {
                    $taxItems = json_decode($transaction->tax_items, true, flags: JSON_THROW_ON_ERROR);
                    $taxCost = array_sum(array_map(
                        static fn (array $taxItem): float => (float) ($taxItem['cost'] ?? 0),
                        $taxItems,
                    ));

                    DB::table('car_transactions')->where('id', $transaction->id)->update([
                        'tax_cost' => $taxCost,
                    ]);
                }
            });

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn('tax_items');
        });
    }
};
