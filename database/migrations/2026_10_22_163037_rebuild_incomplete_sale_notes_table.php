<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $requiredColumns = [
            'car_transaction_id',
            'buyer_name',
            'buyer_nik',
            'buyer_phone',
            'buyer_address',
            'deal_price',
            'payment_method',
            'sell_date',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ];

        if (Schema::hasTable('sale_notes')) {
            $missingColumns = array_diff($requiredColumns, Schema::getColumnListing('sale_notes'));

            if ($missingColumns === []) {
                return;
            }

            if (DB::table('sale_notes')->exists()) {
                throw new RuntimeException(
                    'Cannot rebuild sale_notes: existing rows have no car_transaction_id mapping. '
                    .'Map the rows to vehicles before rerunning this migration.',
                );
            }

            Schema::drop('sale_notes');
        }

        Schema::create('sale_notes', function (Blueprint $table): void {
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

    public function down(): void
    {
        // Keep the repaired table in place; rolling this migration back would break the active module.
    }
};
