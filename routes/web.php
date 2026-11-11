<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MarketingCatalogController as ApiMarketingCatalogController;
use App\Http\Controllers\Api\SaleNoteController as ApiSaleNoteController;
use App\Http\Controllers\Api\SellModuleController as ApiSellModuleController;
use App\Http\Controllers\Api\TransactionController as ApiTransactionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarTransactionController;
use App\Http\Controllers\InitialMasterSetupController;
use App\Http\Controllers\MarketingCatalogController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SaleNoteController;
use App\Http\Controllers\SalePaymentController;
use App\Http\Controllers\SellModuleController;
use App\Http\Controllers\TransactionBackupController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureInitialMasterSetup;
use App\Http\Middleware\RedirectGuestsToMasterSetup;
use App\Models\CarTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['guest', RedirectGuestsToMasterSetup::class])->group(function () {
    Route::get('/login', [AuthController::class, 'create'])
        ->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');
});

Route::middleware([EnsureInitialMasterSetup::class, 'guest'])
    ->controller(InitialMasterSetupController::class)
    ->group(function (): void {
        Route::get('/setup-master', 'create')->name('setup-master.create');
        Route::post('/setup-master', 'store')->name('setup-master.store');
    });

Route::middleware('guest')
    ->prefix('/api/spa')
    ->name('api.spa.')
    ->group(function (): void {
        Route::post('/login', [AuthController::class, 'loginApi'])
            ->middleware('throttle:login')
            ->name('login');
        Route::post('/setup-master', [InitialMasterSetupController::class, 'storeApi'])
            ->middleware(EnsureInitialMasterSetup::class)
            ->name('setup-master');
    });

Route::get('/', [CarTransactionController::class, 'index'])
    ->middleware([RedirectGuestsToMasterSetup::class, 'role.dashboard:master,admin'])
    ->name('dashboard');

Route::redirect('/dashboard', '/')
    ->middleware([RedirectGuestsToMasterSetup::class, 'role.dashboard:master,admin'])
    ->name('dashboard.alias');

Route::post('/logout', [AuthController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::post('/api/spa/logout', [AuthController::class, 'logoutApi'])
    ->middleware('auth')
    ->name('api.spa.logout');

Route::prefix('marketing/stok')
    ->name('marketing.stok.')
    ->middleware(['auth', 'role.dashboard:marketing'])
    ->controller(MarketingCatalogController::class)
    ->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::get('/{transaction}', 'show')->whereNumber('transaction')->name('show');
    });

Route::middleware(['auth', 'role.dashboard:master,admin'])->group(function () {
    Route::get('settings/account', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('settings/account', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('transactions/export', [TransactionBackupController::class, 'export'])
        ->middleware('can:viewSensitiveData,'.CarTransaction::class)
        ->name('transactions.export');
    Route::post('transactions/import', [TransactionBackupController::class, 'import'])
        ->middleware('can:viewSensitiveData,'.CarTransaction::class)
        ->name('transactions.import');
    Route::resource('transactions', CarTransactionController::class)
        ->missing(function (Request $request) {
            return redirect()
                ->route('transactions.index')
                ->with('error', 'Data transaksi tidak ditemukan.');
        });
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
            Route::get('/{saleNote}', 'show')->whereNumber('saleNote')->name('show');
            Route::get('/{saleNote}/edit', 'edit')->whereNumber('saleNote')->name('edit');
            Route::put('/{saleNote}', 'update')->whereNumber('saleNote')->name('update');
            Route::patch('/{saleNote}', 'update')->whereNumber('saleNote')->name('update.patch');
        });
    Route::resource('users', UserController::class)
        ->except(['create', 'show', 'edit'])
        ->middleware('can:viewAny,App\Models\User');
});

Route::get('/api/spa/dashboard', DashboardController::class)
    ->middleware(['auth', 'can:viewAny,'.CarTransaction::class])
    ->name('api.spa.dashboard');

Route::middleware('auth')->prefix('/api/spa')->name('api.spa.')->group(function (): void {
    Route::middleware('role.dashboard:master,admin')->group(function (): void {
        Route::post('/transactions/import', [TransactionBackupController::class, 'import'])
            ->middleware('can:viewSensitiveData,'.CarTransaction::class)
            ->name('transactions.import');
        Route::get('/transactions/{transaction}', [ApiTransactionController::class, 'show'])
            ->whereNumber('transaction')
            ->name('transactions.show');
        Route::post('/transactions', [ApiTransactionController::class, 'store'])
            ->name('transactions.store');
        Route::put('/transactions/{transaction}', [ApiTransactionController::class, 'update'])
            ->whereNumber('transaction')
            ->name('transactions.update');
        Route::delete('/transactions/{transaction}', [ApiTransactionController::class, 'destroy'])
            ->whereNumber('transaction')
            ->name('transactions.destroy');
        Route::prefix('/jual')->name('jual.')->group(function (): void {
            Route::get('/', [ApiSellModuleController::class, 'index'])->name('index');
            Route::get('/create', [ApiSellModuleController::class, 'create'])->name('create');
            Route::post('/', [ApiSellModuleController::class, 'store'])->name('store');
            Route::get('/{buyer}', [ApiSellModuleController::class, 'show'])
                ->whereNumber('buyer')
                ->name('show');
            Route::put('/{buyer}', [ApiSellModuleController::class, 'update'])
                ->whereNumber('buyer')
                ->name('update');
            Route::delete('/{buyer}', [ApiSellModuleController::class, 'destroy'])
                ->whereNumber('buyer')
                ->name('destroy');
            Route::post('/{buyer}/payments', [ApiSellModuleController::class, 'storePayment'])
                ->whereNumber('buyer')
                ->name('payments.store');
        });
        Route::prefix('/sale-notes')->name('sale-notes.')->group(function (): void {
            Route::get('/', [ApiSaleNoteController::class, 'index'])->name('index');
            Route::get('/{saleNote}', [ApiSaleNoteController::class, 'show'])
                ->whereNumber('saleNote')
                ->name('show');
            Route::put('/{saleNote}', [ApiSaleNoteController::class, 'update'])
                ->whereNumber('saleNote')
                ->name('update');
        });
    });
    Route::middleware('role.dashboard:marketing')->prefix('/marketing')->name('marketing.')->group(function (): void {
        Route::get('/vehicles', [ApiMarketingCatalogController::class, 'index'])
            ->name('vehicles.index');
        Route::get('/vehicles/{transaction}', [ApiMarketingCatalogController::class, 'show'])
            ->whereNumber('transaction')
            ->name('vehicles.show');
    });
    Route::get('/users', [App\Http\Controllers\Api\UserController::class, 'index'])
        ->middleware('can:viewAny,App\Models\User')
        ->name('users.index');
    Route::post('/users', [App\Http\Controllers\Api\UserController::class, 'store'])
        ->name('users.store');
    Route::put('/users/{user}', [App\Http\Controllers\Api\UserController::class, 'update'])
        ->name('users.update');
    Route::delete('/users/{user}', [App\Http\Controllers\Api\UserController::class, 'destroy'])
        ->name('users.destroy');
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'updateApi'])
        ->name('profile.update');
});

Route::get('/app/{path?}', fn () => response()->view('spa.app')
    ->header('Cache-Control', 'no-store, private'))
    ->where('path', '.*')
    ->middleware(['auth', 'role.dashboard:master,admin,marketing'])
    ->name('spa.app');
