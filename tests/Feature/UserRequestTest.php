<?php

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('user creation rejects roles outside the supported roles', function () {
    $validator = Validator::make([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'secure-password',
        'role' => 'superuser',
    ], (new StoreUserRequest)->rules());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('role'))->toBeTrue();
});

test('the last master cannot be changed to an admin', function () {
    $managedUser = User::factory()->master()->create();
    $request = new UpdateUserRequest;
    $request->merge(['role' => 'admin']);

    $route = new Route(['PUT'], '/users/{user}', static fn () => null);
    $route->bind(Request::create('/users/'.$managedUser->getKey(), 'PUT'));
    $route->setParameter('user', $managedUser);
    $request->setRouteResolver(static fn () => $route);

    $validator = Validator::make([], []);

    foreach ($request->after() as $callback) {
        $validator->after($callback);
    }

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('role'))->toBeTrue();
});

test('a master can be changed to an admin while another master remains', function () {
    $managedUser = User::factory()->master()->create();
    User::factory()->master()->create();

    $request = new UpdateUserRequest;
    $request->merge(['role' => 'admin']);

    $route = new Route(['PUT'], '/users/{user}', static fn () => null);
    $route->bind(Request::create('/users/'.$managedUser->getKey(), 'PUT'));
    $route->setParameter('user', $managedUser);
    $request->setRouteResolver(static fn () => $route);

    $validator = Validator::make([], []);

    foreach ($request->after() as $callback) {
        $validator->after($callback);
    }

    expect($validator->passes())->toBeTrue();
});
