<?php

use App\Contracts\PostcodeLookup;
use App\Data\PostcodeLocation;
use App\Exceptions\PostcodeLookupUnavailable;
use App\Exceptions\PostcodeNotFound;
use App\Jobs\GeocodeProperty;
use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Services\Postcodes\CachedPostcodeLookup;
use App\Services\Postcodes\FakePostcodeLookup;
use App\Services\Postcodes\PostcodesIoLookup;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;

/**
 * A valid property form submission with the given postcode.
 *
 * @return array<string, mixed>
 */
function propertyFormWithPostcode(string $postcode): array
{
    return [
        'agency_id' => Agency::factory()->create()->id,
        'property_type_id' => PropertyType::factory()->create()->id,
        'title' => 'Two bed flat',
        'description' => 'Bright flat near the station with a balcony.',
        'listing_type' => 'sale',
        'status' => 'available',
        'price' => 250000,
        'bedrooms' => 2,
        'bathrooms' => 1,
        'address_line_1' => '1 Station Road',
        'city' => 'Romford',
        'postcode' => $postcode,
    ];
}

// --- The service container ---------------------------------------------------------

test('the interface resolves to the cached decorator around the configured driver', function () {
    $lookup = app(PostcodeLookup::class);

    expect($lookup)->toBeInstanceOf(CachedPostcodeLookup::class)
        // singleton(): the same object every time it is asked for
        ->and(app(PostcodeLookup::class))->toBe($lookup);
});

test('contextual binding gives PostcodesIoLookup its URL and timeout from config', function () {
    config(['services.postcodes.base_url' => 'https://postcodes.test', 'services.postcodes.timeout' => 7]);
    Http::fake(['postcodes.test/*' => Http::response(['result' => [
        'postcode' => 'SW1A 1AA', 'latitude' => 51.50101, 'longitude' => -0.141563, 'admin_district' => 'Westminster',
    ]])]);

    // Built by the container: it reads both constructor values from config.
    app(PostcodesIoLookup::class)->lookup('SW1A 1AA');

    Http::assertSent(fn ($request) => $request->url() === 'https://postcodes.test/postcodes/SW1A%201AA');
});

test('tests can swap the implementation in the container', function () {
    $stub = new class implements PostcodeLookup
    {
        public function lookup(string $postcode): PostcodeLocation
        {
            return new PostcodeLocation('AB1 2CD', 1.5, 2.5);
        }
    };

    // instance(): "from now on, when anyone asks for PostcodeLookup, give them THIS".
    $this->app->instance(PostcodeLookup::class, $stub);

    expect(app(PostcodeLookup::class)->lookup('anything')->latitude)->toBe(1.5);
});

// --- The real provider, with the HTTP client faked ------------------------------------

test('postcodes.io: a found postcode becomes a PostcodeLocation', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(['status' => 200, 'result' => [
        'postcode' => 'SW1A 1AA', 'latitude' => 51.50101, 'longitude' => -0.141563, 'admin_district' => 'Westminster',
    ]])]);

    $location = app(PostcodesIoLookup::class)->lookup('sw1a1aa');

    expect($location->postcode)->toBe('SW1A 1AA')
        ->and($location->latitude)->toBe(51.50101)
        ->and($location->district)->toBe('Westminster');
});

test('postcodes.io: 404 becomes PostcodeNotFound and is not retried', function () {
    Http::fake(['api.postcodes.io/*' => Http::response(['status' => 404, 'error' => 'Postcode not found'], 404)]);

    expect(fn () => app(PostcodesIoLookup::class)->lookup('ZE9 9ZZ'))->toThrow(PostcodeNotFound::class);
    Http::assertSentCount(1);
});

test('postcodes.io: server errors are retried, then become PostcodeLookupUnavailable', function () {
    Http::fake(['api.postcodes.io/*' => Http::response('Bad gateway', 502)]);

    expect(fn () => app(PostcodesIoLookup::class)->lookup('SW1A 1AA'))->toThrow(PostcodeLookupUnavailable::class);
    Http::assertSentCount(3); // the first try + 2 retries
});

test('postcodes.io: a connection failure becomes PostcodeLookupUnavailable', function () {
    Http::fake(['api.postcodes.io/*' => Http::failedConnection()]);

    expect(fn () => app(PostcodesIoLookup::class)->lookup('SW1A 1AA'))->toThrow(PostcodeLookupUnavailable::class);
});

// --- The caching decorator ------------------------------------------------------------

test('each postcode is looked up once, then served from the cache', function () {
    $fake = new FakePostcodeLookup;
    $cached = new CachedPostcodeLookup($fake);

    $first = $cached->lookup('RM1 1AA');
    $second = $cached->lookup('rm11aa'); // same postcode, different spacing/case

    expect($fake->calls)->toBe(1)
        ->and($second)->toEqual($first);
});

test('failures are never cached', function () {
    $fake = new FakePostcodeLookup;
    $cached = new CachedPostcodeLookup($fake);

    foreach ([1, 2] as $attempt) {
        expect(fn () => $cached->lookup('RM1 9XX'))->toThrow(PostcodeLookupUnavailable::class);
    }

    expect($fake->calls)->toBe(2); // asked again: an outage is not remembered
});

// --- Custom exceptions: reporting and rendering ----------------------------------------

test('PostcodeNotFound is never reported, PostcodeLookupUnavailable is', function () {
    Exceptions::fake();

    report(PostcodeNotFound::for('RM1 9ZZ'));
    report(PostcodeLookupUnavailable::because(new RuntimeException('timeout')));

    Exceptions::assertNotReported(PostcodeNotFound::class);
    Exceptions::assertReported(PostcodeLookupUnavailable::class);
});

test('uncaught exceptions render as 422 / 503 JSON for API clients', function () {
    Route::get('/test/not-found', fn () => throw PostcodeNotFound::for('RM1 9ZZ'));
    Route::get('/test/unavailable', fn () => throw PostcodeLookupUnavailable::because(new RuntimeException('x')));

    $this->getJson('/test/not-found')
        ->assertStatus(422)
        ->assertJsonPath('errors.postcode.0', "We couldn't find the postcode RM1 9ZZ. Please check it.");

    $this->getJson('/test/unavailable')->assertStatus(503);
});

test('the unavailable exception carries the provider error as log context', function () {
    $exception = PostcodeLookupUnavailable::because(new RuntimeException('cURL error 28: timed out'));

    expect($exception->context())->toBe(['provider_error' => 'cURL error 28: timed out'])
        ->and($exception->getPrevious()?->getMessage())->toBe('cURL error 28: timed out');
});

// --- In the property form (Form Request after() with method injection) ------------------

test('a postcode that does not exist is rejected under the postcode field', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.properties.store'), propertyFormWithPostcode('RM1 9ZZ'))
        ->assertSessionHasErrors(['postcode' => "We couldn't find this postcode. Please check it."]);

    expect(Property::count())->toBe(0);
});

test('when the lookup service is down, saving still works and geocoding is queued', function () {
    Queue::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.properties.store'), propertyFormWithPostcode('RM1 9XX'))
        ->assertSessionHasNoErrors();

    expect(Property::count())->toBe(1);
    Queue::assertPushed(GeocodeProperty::class);
});

test('updating geocodes again only when the postcode changed', function () {
    $admin = User::factory()->admin()->create();
    $property = Property::factory()->create(['postcode' => 'RM1 1AA']);
    $form = fn (string $postcode) => [...propertyFormWithPostcode($postcode), 'agency_id' => $property->agency_id];

    Queue::fake();
    $this->actingAs($admin)->put(route('admin.properties.update', $property), $form('RM1 1AA'));
    Queue::assertNotPushed(GeocodeProperty::class);

    $this->put(route('admin.properties.update', $property), $form('SW1A 1AA'));
    Queue::assertPushed(GeocodeProperty::class);
});

// --- The job ---------------------------------------------------------------------------

test('GeocodeProperty stores coordinates without touching updated_at', function () {
    $property = Property::factory()->create(['postcode' => 'RM1 1AA', 'updated_at' => now()->subYear()]);

    GeocodeProperty::dispatchSync($property);

    $fresh = $property->fresh();
    $expected = (new FakePostcodeLookup)->lookup('RM1 1AA');

    expect($fresh->latitude)->toBe($expected->latitude)
        ->and($fresh->longitude)->toBe($expected->longitude)
        ->and($fresh->updated_at->isBefore(now()->subMonths(11)))->toBeTrue();
});

test('GeocodeProperty clears coordinates for an unknown postcode and does not fail', function () {
    $property = Property::factory()->create(['postcode' => 'RM1 9ZZ', 'latitude' => 51.5, 'longitude' => -0.1]);

    $job = (new GeocodeProperty($property))->withFakeQueueInteractions();
    $job->handle(app(PostcodeLookup::class));

    $job->assertNotFailed();
    expect($property->fresh()->latitude)->toBeNull();
});

test('GeocodeProperty lets an outage bubble up so the queue retries it', function () {
    $property = Property::factory()->create(['postcode' => 'RM1 9XX']);

    expect(fn () => (new GeocodeProperty($property))->handle(app(PostcodeLookup::class)))
        ->toThrow(PostcodeLookupUnavailable::class);
});
