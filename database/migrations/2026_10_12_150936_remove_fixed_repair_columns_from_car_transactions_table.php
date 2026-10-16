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
        $legacyRepairs = [
            ['name' => 'Ganti Aki', 'cost' => 'battery_cost', 'date' => 'battery_repair_date'],
            ['name' => 'Service Kaki-kaki', 'cost' => 'undercarriage_cost', 'date' => 'undercarriage_repair_date'],
            ['name' => 'Cat Bumper', 'cost' => 'bumper_paint_cost', 'date' => 'bumper_paint_repair_date'],
            ['name' => 'Ganti Oli', 'cost' => 'oil_change_cost', 'date' => 'oil_change_repair_date'],
        ];

        DB::table('car_transactions')
            ->select(['id', 'additional_repairs', ...array_column($legacyRepairs, 'cost'), ...array_column($legacyRepairs, 'date')])
            ->orderBy('id')
            ->chunkById(100, function ($transactions) use ($legacyRepairs): void {
                foreach ($transactions as $transaction) {
                    $repairs = $transaction->additional_repairs
                        ? json_decode($transaction->additional_repairs, true, flags: JSON_THROW_ON_ERROR)
                        : [];

                    foreach ($repairs as &$repair) {
                        $repair['date'] ??= null;
                        $repair['payment_status'] ??= 'lainnya';
                        $repair['payment_note'] ??= 'Status pembayaran sebelum migrasi tidak tercatat';
                    }
                    unset($repair);

                    foreach ($legacyRepairs as $legacyRepair) {
                        $cost = (float) $transaction->{$legacyRepair['cost']};

                        if ($cost <= 0) {
                            continue;
                        }

                        $repairs[] = [
                            'name' => $legacyRepair['name'],
                            'date' => $transaction->{$legacyRepair['date']} ?? null,
                            'cost' => $cost,
                            'payment_status' => 'lainnya',
                            'payment_note' => 'Status pembayaran sebelum migrasi tidak tercatat',
                        ];
                    }

                    DB::table('car_transactions')
                        ->where('id', $transaction->id)
                        ->update(['additional_repairs' => json_encode($repairs, JSON_THROW_ON_ERROR)]);
                }
            });

        Schema::table('car_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'battery_cost',
                'undercarriage_cost',
                'bumper_paint_cost',
                'oil_change_cost',
                'battery_repair_date',
                'undercarriage_repair_date',
                'bumper_paint_repair_date',
                'oil_change_repair_date',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table) {
            $table->decimal('battery_cost', 15, 2)->default(0);
            $table->decimal('undercarriage_cost', 15, 2)->default(0);
            $table->decimal('bumper_paint_cost', 15, 2)->default(0);
            $table->decimal('oil_change_cost', 15, 2)->default(0);
            $table->date('battery_repair_date')->nullable();
            $table->date('undercarriage_repair_date')->nullable();
            $table->date('bumper_paint_repair_date')->nullable();
            $table->date('oil_change_repair_date')->nullable();
        });
    }
};
