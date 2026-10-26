<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

test('the device list shows only my active tokens and marks the current one', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('phone')->plainTextToken;
    $user->createToken('laptop');
    $user->createToken('old tablet', ['*'], now()->subDay());
    User::factory()->create()->createToken('someone else');

    $response = $this->withToken($phone)
        ->getJson(route('api.v1.tokens.index'))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonMissingPath('data.0.token');

    $current = collect($response->json('data'))->firstWhere('is_current', true);
    expect($current['name'])->toBe('phone');
});

test('a user can log out one of their other devices', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('phone')->plainTextToken;
    $laptop = $user->createToken('laptop')->accessToken;

    $this->withToken($phone)
        ->deleteJson(route('api.v1.tokens.destroy', $laptop->id))
        ->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['phone']);
});

test("another user's token cannot be deleted", function () {
    $phone = User::factory()->create()->createToken('phone')->plainTextToken;
    $theirs = User::factory()->create()->createToken('theirs')->accessToken;

    $this->withToken($phone)
        ->deleteJson(route('api.v1.tokens.destroy', $theirs->id))
        ->assertNotFound();

    expect($theirs->fresh())->not->toBeNull();
});

test('logout-all deletes every token of the user only', function () {
    $user = User::factory()->create();
    $phone = $user->createToken('phone')->plainTextToken;
    $user->createToken('laptop');
    $other = User::factory()->create();
    $other->createToken('theirs');

    $this->withToken($phone)
        ->postJson(route('api.v1.logout-all'))
        ->assertNoContent();

    expect($user->tokens()->count())->toBe(0)
        ->and($other->tokens()->count())->toBe(1);
});

test('expired tokens are pruned by the scheduled command', function () {
    $user = User::factory()->create();
    $user->createToken('active');
    $user->createToken('expired', ['*'], now()->subDays(2));

    Artisan::call('sanctum:prune-expired', ['--hours' => 24]);

    expect($user->tokens()->pluck('name')->all())->toBe(['active']);
});
