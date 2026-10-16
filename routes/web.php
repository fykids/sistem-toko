<?php

use App\Http\Controllers\CarTransactionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [CarTransactionController::class, 'index']);
Route::resource('transactions', CarTransactionController::class);