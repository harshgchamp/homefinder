<?php

use App\Models\User;

test('a user can log in and receives a token', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.v1.login'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'Pest',
    ])
        ->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonStructure(['token', 'expires_at', 'user' => ['id', 'name', 'email', 'email_verified']]);

    expect($user->tokens()->count())->toBe(1);
});

test('a wrong password returns 422 and creates no token', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.v1.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Pest',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    expect($user->tokens()->count())->toBe(0);
});

test('me returns the owner of the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Pest')->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.v1.me'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonMissingPath('data.password');
});

test('me without a token returns 401', function () {
    $this->getJson(route('api.v1.me'))->assertUnauthorized();
});

test('logout deletes only the current token', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('phone')->plainTextToken;
    $user->createToken('laptop');

    $this->withToken($phone)
        ->postJson(route('api.v1.logout'))
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['laptop']);
});

test('the sixth login attempt within a minute gets 429', function () {
    $user = User::factory()->create();
    $attempt = fn () => $this->postJson(route('api.v1.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'Pest',
    ]);

    foreach (range(1, 5) as $i) {
        $attempt()->assertUnprocessable();
    }

    $attempt()->assertTooManyRequests();
});
