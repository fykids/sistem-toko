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
        $requiredColumns = [
            'id',
            'sale_note_id',
            'amount',
            'payment_method',
            'payment_date',
            'notes',
            'recorded_by',
            'created_at',
            'updated_at',
        ];

        if (Schema::hasTable('sale_payments')) {
            $columns = Schema::getColumnListing('sale_payments');
            $foreignKeys = Schema::getForeignKeys('sale_payments');
            $hasSaleNoteForeignKey = $this->hasForeignKey($foreignKeys, 'sale_note_id', 'sale_notes');
            $hasRecorderForeignKey = $this->hasForeignKey($foreignKeys, 'recorded_by', 'users');

            if (
                array_diff($requiredColumns, $columns) === []
                && $hasSaleNoteForeignKey
                && $hasRecorderForeignKey
            ) {
                return;
            }

            if (DB::table('sale_payments')->exists()) {
                throw new RuntimeException(
                    'The sale_payments table has an incomplete schema and contains data. '
                    .'Resolve the schema mismatch without discarding payment records before migrating.',
                );
            }

            Schema::drop('sale_payments');
        }

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sale_note_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 30);
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_note_id', 'payment_date', 'id']);
        });
    }

    /**
     * @param  array<int, array{columns: array<int, string>, foreign_table: string, foreign_columns: array<int, string>}>  $foreignKeys
     */
    private function hasForeignKey(array $foreignKeys, string $column, string $table): bool
    {
        foreach ($foreignKeys as $foreignKey) {
            if (
                $foreignKey['columns'] === [$column]
                && $foreignKey['foreign_table'] === $table
                && $foreignKey['foreign_columns'] === ['id']
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('The sale_payments schema repair cannot be safely reversed.');
    }
};
