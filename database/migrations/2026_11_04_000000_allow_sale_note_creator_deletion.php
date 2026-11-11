<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
        });

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        $hasCreatorIndex = collect(Schema::getIndexes('sale_notes'))->contains(
            static fn (array $index): bool => ($index['columns'][0] ?? null) === 'created_by',
        );

        if (! $hasCreatorIndex) {
            Schema::table('sale_notes', function (Blueprint $table): void {
                $table->index('created_by', 'sale_notes_creator_index');
            });
        }
    }

    public function down(): void
    {
        if (DB::table('sale_notes')->whereNull('created_by')->exists()) {
            throw new RuntimeException(
                'Cannot restore the required sale note creator while sale notes have no creator.',
            );
        }

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
        });

        if (Schema::hasIndex('sale_notes', 'sale_notes_creator_index')) {
            Schema::table('sale_notes', function (Blueprint $table): void {
                $table->dropIndex('sale_notes_creator_index');
            });
        }

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
        });

        Schema::table('sale_notes', function (Blueprint $table): void {
            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->restrictOnDelete();
        });
    }
};
