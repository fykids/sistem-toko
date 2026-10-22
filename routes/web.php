<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarTransactionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TransactionBackupController;
use App\Http\Controllers\UserController;
use App\Models\CarTransaction;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/', [CarTransactionController::class, 'index'])->name('dashboard');
    Route::get('settings/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('settings/account', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('transactions/export', [TransactionBackupController::class, 'export'])
        ->middleware('can:viewSensitiveData,'.CarTransaction::class)
        ->name('transactions.export');
    Route::post('transactions/import', [TransactionBackupController::class, 'import'])
        ->middleware('can:viewSensitiveData,'.CarTransaction::class)
        ->name('transactions.import');
    Route::resource('transactions', CarTransactionController::class);
    Route::resource('users', UserController::class)
        ->except(['create', 'show', 'edit'])
        ->middleware('can:viewAny,App\Models\User');
});
