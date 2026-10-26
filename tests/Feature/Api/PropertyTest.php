<?php

use App\Models\Property;

test('the list shows only published listings', function () {
    $published = Property::factory()->published()->create();
    Property::factory()->draft()->create();
    Property::factory()->scheduled()->create();
    Property::factory()->published()->create()->delete();

    $this->getJson(route('api.v1.properties.index'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id)
        ->assertJsonMissingPath('data.0.description')
        ->assertJsonMissingPath('data.0.agency_id');
});

test('the list can be filtered by city and price', function () {
    $match = Property::factory()->published()->create(['city' => 'Leeds', 'price' => 300_000]);
    Property::factory()->published()->create(['city' => 'Leeds', 'price' => 100_000]);
    Property::factory()->published()->create(['city' => 'York', 'price' => 300_000]);

    $this->getJson(route('api.v1.properties.index', ['city' => 'Leeds', 'min_price' => 200_000]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $match->id);
});

test('admin-only filters are ignored, so trashed listings stay hidden', function () {
    Property::factory()->published()->create()->delete();

    $this->getJson(route('api.v1.properties.index', ['trashed' => 'with', 'publication' => 'draft']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('an unknown sort is rejected with 422', function () {
    $this->getJson(route('api.v1.properties.index', ['sort' => 'password']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sort');
});

test('the list uses cursor pagination', function () {
    Property::factory()->published()->count(3)->create();

    $first = $this->getJson(route('api.v1.properties.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['meta' => ['next_cursor', 'prev_cursor', 'per_page']]);

    $this->getJson(route('api.v1.properties.index', ['per_page' => 2, 'cursor' => $first->json('meta.next_cursor')]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.next_cursor', null);
});

test('a published listing can be viewed by slug', function () {
    $property = Property::factory()->published()->create();

    $this->getJson(route('api.v1.properties.show', $property->slug))
        ->assertOk()
        ->assertJsonPath('data.id', $property->id)
        ->assertJsonStructure(['data' => ['description', 'agency', 'property_type', 'features', 'images']]);
});

test('a draft listing returns 404', function () {
    $property = Property::factory()->draft()->create();

    $this->getJson(route('api.v1.properties.show', $property->slug))->assertNotFound();
});
