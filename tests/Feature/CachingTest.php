<?php

use App\Models\Agency;
use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Support\CachedLookups;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Run $callback and return how many database queries it made.
 */
function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $callback();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

// --- Cache::remember for lookup lists ------------------------------------------------

test('lookup lists hit the database once, then come from the cache', function () {
    Agency::factory()->count(3)->create();

    expect(countQueries(fn () => CachedLookups::agencies()))->toBe(1)
        ->and(countQueries(fn () => CachedLookups::agencies()))->toBe(0)
        ->and(Cache::has(CachedLookups::AGENCIES))->toBeTrue()
        ->and(CachedLookups::agencies())->toHaveCount(3);
});

test('saving or deleting a model clears its cached list', function (string $model, Closure $read) {
    $first = $model::factory()->create(['name' => 'Alpha']);
    $read();
    expect(Cache::has($model::lookupCacheKey()))->toBeTrue();

    // created -> cleared
    $model::factory()->create(['name' => 'Beta']);
    expect(Cache::has($model::lookupCacheKey()))->toBeFalse()
        ->and(array_column($read(), 'label'))->toContain('Beta');

    // updated -> cleared
    $first->update(['name' => 'Alpha renamed']);
    expect(array_column($read(), 'label'))->toContain('Alpha renamed');

    // deleted -> cleared
    $first->delete();
    expect(array_column($read(), 'label'))->not->toContain('Alpha renamed');
})->with([
    'agency' => [Agency::class, fn () => CachedLookups::agencies()],
    'property type' => [PropertyType::class, fn () => CachedLookups::propertyTypes()],
    'feature' => [Feature::class, fn () => CachedLookups::features()],
]);

test('a rolled-back change does not clear the cache', function () {
    Agency::factory()->create(['name' => 'Kept']);
    CachedLookups::agencies();

    try {
        DB::transaction(function (): void {
            Agency::factory()->create(['name' => 'Never committed']);
            throw new RuntimeException('roll back');
        });
    } catch (RuntimeException) {
        // expected
    }

    // DB::afterCommit(): the clear only runs on COMMIT, so the cache is untouched.
    expect(Cache::has(CachedLookups::AGENCIES))->toBeTrue();
});

test('the property form reads the cached lists and narrows them for agents', function () {
    [$own, $other] = Agency::factory()->count(2)->create();
    $agent = User::factory()->agent($own)->create();

    $this->actingAs($agent)
        ->get(route('admin.properties.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('options.agencies', 1)
            ->where('options.agencies.0.value', $own->id));

    // The shared cached list still holds every active agency, for admins.
    expect(array_column(CachedLookups::agencies(), 'value'))->toContain($own->id, $other->id);
});

// --- Cache::flexible for dashboard stats -----------------------------------------------

test('dashboard stats are cached for a minute, then recalculated', function () {
    $admin = User::factory()->admin()->create();
    Property::factory()->published()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.published', 1));

    // A new listing appears, but the cached numbers are still served.
    Property::factory()->published()->create();
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.published', 1));

    // Past the stale window (300s), the value is recalculated before responding.
    $this->travel(301)->seconds();
    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.published', 2));
});

test('agents never get the admin\'s cached numbers', function () {
    $agency = Agency::factory()->create();
    Property::factory()->published()->for($agency)->create();
    Property::factory()->published()->count(4)->create(); // other agencies

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.published', 5));

    $this->actingAs(User::factory()->agent($agency)->create())->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.published', 1));

    expect(Cache::has('dashboard:stats:all'))->toBeTrue()
        ->and(Cache::has("dashboard:stats:agency:{$agency->id}"))->toBeTrue();
});

// --- Cache::lock for exports ------------------------------------------------------------

test('a second export while one is running is refused', function () {
    $admin = User::factory()->admin()->create();

    // Pretend an export is already streaming for this user.
    $running = Cache::lock("exports:user:{$admin->id}", 300);
    expect($running->get())->toBeTrue();

    $this->actingAs($admin)
        ->from(route('admin.properties.index'))
        ->get(route('admin.properties.export'))
        ->assertRedirect(route('admin.properties.index'));

    $running->release();

    $this->get(route('admin.properties.export'))->assertOk();
});

test('the lock is released when the export finishes', function () {
    $admin = User::factory()->admin()->create();
    Property::factory()->count(3)->create();

    $this->actingAs($admin)->get(route('admin.properties.export'))->streamedContent();

    expect(Cache::lock("exports:user:{$admin->id}", 300)->get())->toBeTrue();
});
