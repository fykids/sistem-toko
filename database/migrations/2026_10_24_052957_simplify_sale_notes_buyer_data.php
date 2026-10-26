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
        $legacyColumns = array_values(array_filter(
            ['buyer_nik', 'buyer_phone', 'buyer_address', 'payment_method', 'notes'],
            static fn (string $column): bool => Schema::hasColumn('sale_notes', $column),
        ));

        if ($legacyColumns !== []) {
            Schema::table('sale_notes', function (Blueprint $table) use ($legacyColumns): void {
                $table->dropColumn($legacyColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_notes', function (Blueprint $table): void {
            if (! Schema::hasColumn('sale_notes', 'buyer_nik')) {
                $table->string('buyer_nik')->nullable();
            }

            if (! Schema::hasColumn('sale_notes', 'buyer_phone')) {
                $table->string('buyer_phone')->nullable();
            }

            if (! Schema::hasColumn('sale_notes', 'buyer_address')) {
                $table->text('buyer_address')->nullable();
            }

            if (! Schema::hasColumn('sale_notes', 'payment_method')) {
                $table->string('payment_method')->default('cash');
            }

            if (! Schema::hasColumn('sale_notes', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }
};
