<?php

use App\Data\Coordinates;

/*
 * A real UNIT test: no database, no HTTP, no Laravel app is booted
 * (tests/Pest.php only extends Tests\TestCase for the Feature folder).
 * A value object has no dependencies, so it can be tested this fast.
 */

test('a valid point keeps its values', function () {
    $point = new Coordinates(51.5074, -0.1278);

    expect($point->latitude)->toBe(51.5074)
        ->and($point->longitude)->toBe(-0.1278)
        ->and($point->toArray())->toBe(['latitude' => 51.5074, 'longitude' => -0.1278]);
});

test('the edges of the valid range are accepted', function () {
    expect(new Coordinates(90, 180))->toBeInstanceOf(Coordinates::class)
        ->and(new Coordinates(-90, -180))->toBeInstanceOf(Coordinates::class);
});

test('an invalid point cannot be created', function (float $latitude, float $longitude) {
    new Coordinates($latitude, $longitude);
})->throws(InvalidArgumentException::class)->with([
    'latitude too high' => [90.1, 0],
    'latitude too low' => [-90.1, 0],
    'longitude too high' => [0, 180.1],
    'longitude too low' => [0, -180.1],
]);

test('distanceTo uses the haversine formula: London to Paris is about 344 km', function () {
    $london = new Coordinates(51.5074, -0.1278);
    $paris = new Coordinates(48.8566, 2.3522);

    expect($london->distanceTo($paris))->toEqualWithDelta(343.5, 1.0)
        // The same both ways, and zero to itself.
        ->and($paris->distanceTo($london))->toEqualWithDelta($london->distanceTo($paris), 0.0001)
        ->and($london->distanceTo($london))->toBe(0.0);
});

test('two points with the same values are equal', function () {
    expect((new Coordinates(51.5, -0.1))->equals(new Coordinates(51.5, -0.1)))->toBeTrue()
        ->and((new Coordinates(51.5, -0.1))->equals(new Coordinates(51.5, -0.2)))->toBeFalse();
});

test('json_encode gives latitude and longitude', function () {
    expect(json_encode(new Coordinates(51.5, -0.1)))->toBe('{"latitude":51.5,"longitude":-0.1}');
});
