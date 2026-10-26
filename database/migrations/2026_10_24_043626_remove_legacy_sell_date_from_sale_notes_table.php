<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sale_notes', 'sell_date')) {
            return;
        }

        DB::table('sale_notes')
            ->select(['id', 'car_transaction_id', 'sell_date'])
            ->orderBy('id')
            ->chunkById(500, function (Collection $saleNotes): void {
                foreach ($saleNotes as $saleNote) {
                    DB::table('car_transactions')
                        ->where('id', $saleNote->car_transaction_id)
                        ->whereNull('sell_date')
                        ->update(['sell_date' => $saleNote->sell_date]);
                }
            });

        DB::statement('DROP INDEX IF EXISTS sale_notes_sell_date_index');

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->dropColumn('sell_date');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_notes', 'sell_date')) {
            return;
        }

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->date('sell_date')->nullable()->index();
        });

        DB::table('sale_notes')
            ->join('car_transactions', 'sale_notes.car_transaction_id', '=', 'car_transactions.id')
            ->select(['sale_notes.id as id', 'car_transactions.sell_date'])
            ->orderBy('sale_notes.id')
            ->chunkById(500, function (Collection $saleNotes): void {
                foreach ($saleNotes as $saleNote) {
                    DB::table('sale_notes')
                        ->where('id', $saleNote->id)
                        ->update(['sell_date' => $saleNote->sell_date]);
                }
            }, 'sale_notes.id', 'id');
    }
};
