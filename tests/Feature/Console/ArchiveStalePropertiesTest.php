<?php

use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyType;

test('unpublishes stale available listings and leaves the rest alone', function () {
    $stale = Property::factory()->create(['published_at' => now()->subDays(100)]);
    $recent = Property::factory()->create(['published_at' => now()->subDays(10)]);
    $sold = Property::factory()->sold()->create(['published_at' => now()->subDays(100)]);
    $draft = Property::factory()->draft()->create();

    $this->artisan('properties:archive-stale')
        ->expectsOutputToContain('Archived 1 listings.')
        ->assertSuccessful();

    expect($stale->fresh()->published_at)->toBeNull()
        ->and($recent->fresh()->published_at)->not->toBeNull()
        ->and($sold->fresh()->published_at)->not->toBeNull()
        ->and($draft->fresh()->published_at)->toBeNull();
});

test('dry run reports but changes nothing', function () {
    $stale = Property::factory()->create(['published_at' => now()->subDays(100)]);

    $this->artisan('properties:archive-stale', ['--dry-run' => true])
        ->expectsOutputToContain('Would archive 1 listings.')
        ->assertSuccessful();

    expect($stale->fresh()->published_at)->not->toBeNull();
});

test('the --days option changes the cut-off', function () {
    $property = Property::factory()->create(['published_at' => now()->subDays(40)]);

    $this->artisan('properties:archive-stale', ['--days' => 30])->assertSuccessful();

    expect($property->fresh()->published_at)->toBeNull();
});

test('processes every row across several chunks', function () {
    // 250 rows with chunks of 200. With chunk() (OFFSET paging) the second page
    // would skip the last 50, because the first 200 no longer match the query.
    // chunkById() gets all 250.
    Property::factory()
        ->count(250)
        ->recycle(Agency::factory()->create())
        ->recycle(PropertyType::factory()->create())
        ->create(['published_at' => now()->subDays(100)]);

    $this->artisan('properties:archive-stale')->assertSuccessful();

    expect(Property::query()->whereNotNull('published_at')->count())->toBe(0);
});

test('rejects an invalid --days value', function () {
    $this->artisan('properties:archive-stale', ['--days' => 0])->assertFailed();
});
