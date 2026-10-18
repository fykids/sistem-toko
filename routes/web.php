<?php

use App\Http\Controllers\CarTransactionController;
use App\Http\Controllers\TransactionBackupController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CarTransactionController::class, 'index']);
Route::get('transactions/export', [TransactionBackupController::class, 'export'])->name('transactions.export');
Route::post('transactions/import', [TransactionBackupController::class, 'import'])->name('transactions.import');
Route::resource('transactions', CarTransactionController::class);
