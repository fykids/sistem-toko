<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

test('database query failures do not expose SQL details in the response', function () {
    Route::get('/database-failure-test', function () {
        DB::table('table_that_does_not_exist')->get();
    });

    $this->get('/database-failure-test')
        ->assertInternalServerError()
        ->assertSee('Terjadi kesalahan saat memproses data.')
        ->assertDontSee('table_that_does_not_exist');
});
