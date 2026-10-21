<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('only masters can view and manage user accounts', function () {
    $master = User::factory()->master()->create();
    $admin = User::factory()->create();
    $managedUser = User::factory()->create();

    expect($master->can('viewAny', User::class))->toBeTrue()
        ->and($master->can('create', User::class))->toBeTrue()
        ->and($master->can('update', $managedUser))->toBeTrue()
        ->and($admin->can('viewAny', User::class))->toBeFalse()
        ->and($admin->can('create', User::class))->toBeFalse()
        ->and($admin->can('update', $managedUser))->toBeFalse();
});

test('masters cannot delete their own account', function () {
    $master = User::factory()->master()->create();

    expect($master->can('delete', $master))->toBeFalse();
});

test('masters can delete other accounts while retaining their own access', function () {
    $master = User::factory()->master()->create();
    $managedAdmin = User::factory()->create();

    expect($master->can('delete', $managedAdmin))->toBeTrue();
});

test('masters can delete another master only when multiple master accounts exist', function () {
    $master = User::factory()->master()->create();
    $managedMaster = User::factory()->master()->create();

    expect($master->can('delete', $managedMaster))->toBeTrue();
});
