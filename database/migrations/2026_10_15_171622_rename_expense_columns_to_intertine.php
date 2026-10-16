<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('car_transactions', function (Blueprint $table): void {
            $table->renameColumn('internal_expenses', 'intertine_expenses');
            $table->renameColumn('total_internal_expense', 'total_intertine_expense');
        });
    }

    public function down(): void
    {
        Schema::table('car_transactions', function (Blueprint $table): void {
            $table->renameColumn('intertine_expenses', 'internal_expenses');
            $table->renameColumn('total_intertine_expense', 'total_internal_expense');
        });
    }
};
