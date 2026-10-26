<?php

use App\Models\Property;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('favourites need a token', function () {
    $property = Property::factory()->published()->create();

    $this->getJson(route('api.v1.favourites.index'))->assertUnauthorized();
    $this->putJson(route('api.v1.favourites.store', $property->slug))->assertUnauthorized();
});

test('adding a favourite twice keeps one row', function () {
    $user = User::factory()->create();
    $property = Property::factory()->published()->create();
    Sanctum::actingAs($user, ['*']);

    $this->putJson(route('api.v1.favourites.store', $property->slug))->assertNoContent();
    $this->putJson(route('api.v1.favourites.store', $property->slug))->assertNoContent();

    expect($user->favourites()->count())->toBe(1);
});

test('a favourite can be removed, and removing it again is still 204', function () {
    $user = User::factory()->create();
    $property = Property::factory()->published()->create();
    $user->favourites()->attach($property);
    Sanctum::actingAs($user, ['*']);

    $this->deleteJson(route('api.v1.favourites.destroy', $property->slug))->assertNoContent();
    $this->deleteJson(route('api.v1.favourites.destroy', $property->slug))->assertNoContent();

    expect($user->favourites()->count())->toBe(0);
});

test('a read-only token cannot change favourites', function () {
    $property = Property::factory()->published()->create();
    Sanctum::actingAs(User::factory()->create(), ['properties:read']);

    $this->putJson(route('api.v1.favourites.store', $property->slug))->assertForbidden();
});

test('a draft listing cannot be favourited', function () {
    $property = Property::factory()->draft()->create();
    Sanctum::actingAs(User::factory()->create(), ['*']);

    $this->putJson(route('api.v1.favourites.store', $property->slug))->assertNotFound();
});

test('the list shows only my published favourites', function () {
    $user = User::factory()->create();
    $mine = Property::factory()->published()->create();
    $user->favourites()->attach([$mine->id, Property::factory()->draft()->create()->id]);
    User::factory()->create()->favourites()->attach(Property::factory()->published()->create());
    Sanctum::actingAs($user);

    $this->getJson(route('api.v1.favourites.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('meta.total', 1);
});
