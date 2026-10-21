<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('new users default to the admin role', function () {
    $user = User::factory()->create();

    expect($user->refresh()->role)->toBe('admin');
});
