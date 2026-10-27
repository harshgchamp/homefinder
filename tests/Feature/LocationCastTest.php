<?php

use App\Data\Coordinates;
use App\Data\PostcodeLocation;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('location reads both columns as one Coordinates object', function () {
    $property = Property::factory()->create(['latitude' => 51.5754, 'longitude' => 0.1821]);

    // fresh(): read back from the database, where decimals arrive as strings.
    $location = $property->fresh()->location;

    expect($location)->toBeInstanceOf(Coordinates::class)
        ->and($location->latitude)->toBe(51.5754)
        ->and($location->longitude)->toBe(0.1821);
});

test('location is null until the property is geocoded', function () {
    $property = Property::factory()->create(['latitude' => null, 'longitude' => null]);

    expect($property->fresh()->location)->toBeNull();
});

test('setting location writes both columns, and null clears both', function () {
    $property = Property::factory()->create(['latitude' => null, 'longitude' => null]);

    $property->location = new Coordinates(51.5, -0.1);
    $property->save();

    expect(DB::table('properties')->where('id', $property->id)->first(['latitude', 'longitude']))
        ->latitude->toEqual('51.5000000')
        ->longitude->toEqual('-0.1000000');

    $property->location = null;
    $property->save();

    expect($property->fresh())
        ->latitude->toBeNull()
        ->longitude->toBeNull();
});

test('changing a column refreshes the location object', function () {
    $property = Property::factory()->create(['latitude' => 51.5, 'longitude' => -0.1]);
    expect($property->location->latitude)->toBe(51.5);

    $property->latitude = 52.0;

    expect($property->location->latitude)->toBe(52.0);
});

test('only Coordinates or null can be assigned', function () {
    $property = Property::factory()->make();

    $property->location = ['latitude' => 51.5, 'longitude' => -0.1];
})->throws(InvalidArgumentException::class);

test('a postcode lookup answer converts to coordinates for a property', function () {
    $answer = new PostcodeLocation('SW1A 1AA', 51.50101, -0.141563, 'Westminster');
    $property = Property::factory()->create(['latitude' => null, 'longitude' => null]);

    // Assigned, not update([...]): location is deliberately not in $fillable.
    $property->location = $answer->coordinates();
    $property->save();

    expect($property->fresh()->location->equals(new Coordinates(51.50101, -0.141563)))->toBeTrue();
});

test('the API and admin resources still send the same location JSON', function () {
    $property = Property::factory()->published()->create(['latitude' => 51.5754, 'longitude' => 0.1821]);
    $expected = ['latitude' => 51.5754, 'longitude' => 0.1821];

    $this->getJson(route('api.v1.properties.show', $property->slug))
        ->assertOk()
        ->assertJsonPath('data.location', $expected);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.properties.show', $property))
        ->assertInertia(fn ($page) => $page->where('property.location', $expected));
});
