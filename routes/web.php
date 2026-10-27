<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarTransactionController;
use App\Http\Controllers\MarketingCatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleNoteController;
use App\Http\Controllers\SalePaymentController;
use App\Http\Controllers\SellModuleController;
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

Route::prefix('marketing/stok')
    ->name('marketing.stok.')
    ->middleware(['auth', 'role.dashboard:marketing'])
    ->controller(MarketingCatalogController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{transaction}', 'show')->whereNumber('transaction')->name('show');
    });

Route::middleware(['auth', 'role.dashboard:master,admin'])->group(function () {
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
    Route::prefix('jual')
        ->name('jual.')
        ->controller(SellModuleController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{buyer}/pelunasan', [SalePaymentController::class, 'create'])
                ->name('payments.create');
            Route::post('/{buyer}/pelunasan', [SalePaymentController::class, 'store'])
                ->name('payments.store');
            Route::get('/{buyer}', 'show')->name('show');
            Route::get('/{buyer}/edit', 'edit')->name('edit');
            Route::put('/{buyer}', 'update')->name('update');
            Route::delete('/{buyer}', 'destroy')->name('destroy');
        });
    Route::prefix('nota-penjualan')
        ->name('nota-penjualan.')
        ->controller(SaleNoteController::class)
        ->group(function (): void {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{saleNote}', 'show')->name('show');
            Route::get('/{saleNote}/edit', 'edit')->name('edit');
            Route::put('/{saleNote}', 'update')->name('update');
            Route::patch('/{saleNote}', 'update')->name('update.patch');
        });
    Route::resource('users', UserController::class)
        ->except(['create', 'show', 'edit'])
        ->middleware('can:viewAny,App\Models\User');
});
