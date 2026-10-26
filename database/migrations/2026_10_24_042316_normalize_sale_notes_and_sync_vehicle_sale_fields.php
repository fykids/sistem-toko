<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sale_notes', 'buyer_name')) {
            Schema::table('sale_notes', function (Blueprint $table): void {
                $table->renameColumn('buyer_name', 'nama_konsumen');
            });
        }

        $legacyColumns = array_values(array_filter(
            ['deal_price', 'sell_date'],
            static fn (string $column): bool => Schema::hasColumn('sale_notes', $column),
        ));

        if ($legacyColumns !== []) {
            $columnsToRead = ['car_transaction_id', ...$legacyColumns];
            DB::table('sale_notes')
                ->orderBy('id')
                ->get($columnsToRead)
                ->each(function (object $saleNote): void {
                    $vehicleAttributes = [];

                    if (property_exists($saleNote, 'deal_price')) {
                        $vehicleAttributes['selling_price'] = $saleNote->deal_price;
                    }

                    if (property_exists($saleNote, 'sell_date')) {
                        $vehicleAttributes['sell_date'] = $saleNote->sell_date;
                    }

                    if ($vehicleAttributes === []) {
                        return;
                    }

                    DB::table('car_transactions')
                        ->where('id', $saleNote->car_transaction_id)
                        ->whereNull('sell_date')
                        ->update($vehicleAttributes);
                });

            DB::statement('DROP INDEX IF EXISTS sale_notes_sell_date_index');

            Schema::table('sale_notes', function (Blueprint $table) use ($legacyColumns): void {
                $table->dropColumn($legacyColumns);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('sale_notes', 'deal_price')) {
            Schema::table('sale_notes', function (Blueprint $table): void {
                $table->decimal('deal_price', 15, 2)->default(0);

                if (! Schema::hasColumn('sale_notes', 'sell_date')) {
                    $table->date('sell_date')->nullable()->index();
                }
            });

            DB::table('sale_notes')
                ->join('car_transactions', 'sale_notes.car_transaction_id', '=', 'car_transactions.id')
                ->select([
                    'sale_notes.id',
                    'car_transactions.selling_price',
                    'car_transactions.sell_date',
                ])
                ->orderBy('sale_notes.id')
                ->get()
                ->each(function (object $saleNote): void {
                    DB::table('sale_notes')
                        ->where('id', $saleNote->id)
                        ->update([
                            'deal_price' => $saleNote->selling_price,
                            'sell_date' => $saleNote->sell_date,
                        ]);
                });
        }

        if (Schema::hasColumn('sale_notes', 'nama_konsumen')) {
            Schema::table('sale_notes', function (Blueprint $table): void {
                $table->renameColumn('nama_konsumen', 'buyer_name');
            });
        }
    }
};
