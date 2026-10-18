<?php

use App\Events\EnquiryReceived;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Only testing the limiter here, not the listeners.
    Event::fake([EnquiryReceived::class]);
});

/**
 * POST one enquiry about a NEW property (the 24-hour "one per property" rule
 * would otherwise stop the second request before the limiter is reached).
 */
function sendEnquiry(User $user): TestResponse
{
    $property = Property::factory()->published()->create();

    return test()->actingAs($user)
        ->from(route('properties.show', $property->slug))
        ->post(route('properties.enquiries.store', $property->slug), ['message' => 'Is a viewing possible this week?']);
}

test('the sixth enquiry within a minute gets a friendly error, not a 429 page', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $i) {
        sendEnquiry($user)->assertSessionHasNoErrors();
    }

    sendEnquiry($user)
        ->assertRedirect()
        ->assertSessionHasErrors(['message' => 'You are sending enquiries too quickly. Please wait a little and try again.'])
        // The standard rate limit headers are still there for API-style clients.
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '5');
});

test('the limit is per user: another user is not affected', function () {
    $busy = User::factory()->create();
    foreach (range(1, 6) as $i) {
        sendEnquiry($busy);
    }

    sendEnquiry(User::factory()->create())->assertSessionHasNoErrors();
});

test('the minute limit resets after a minute; the day limit is counted separately', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $i) {
        sendEnquiry($user);
    }
    sendEnquiry($user)->assertSessionHasErrors('message');

    // travel(): move the test clock forward. The per-minute counter has
    // expired, and the per-day counter (6 of 20 used) still allows more.
    $this->travel(61)->seconds();

    sendEnquiry($user)->assertSessionHasNoErrors();
});

test('the day limit stops the 21st enquiry even when spread out', function () {
    $user = User::factory()->create();

    foreach (range(1, 20) as $i) {
        sendEnquiry($user)->assertSessionHasNoErrors();
        $this->travel(61)->seconds(); // stay under the per-minute limit
    }

    sendEnquiry($user)->assertSessionHasErrors('message');
});

test('the named limiters are registered and used by the routes', function () {
    foreach (['enquiries', 'admin-writes', 'exports', 'api'] as $name) {
        expect(RateLimiter::limiter($name))->not->toBeNull("limiter {$name}");
    }

    expect(Route::getRoutes()->getByName('properties.enquiries.store')->gatherMiddleware())
        ->toContain('throttle:enquiries')
        ->and(Route::getRoutes()->getByName('admin.properties.store')->gatherMiddleware())
        ->toContain('throttle:admin-writes')
        ->and(Route::getRoutes()->getByName('admin.properties.export')->gatherMiddleware())
        ->toContain('throttle:exports');
});

test('the api limiter counts per user when logged in and per IP otherwise', function () {
    $limiter = RateLimiter::limiter('api');
    $request = Request::create('/api/v1/properties', server: ['REMOTE_ADDR' => '203.0.113.7']);

    expect($limiter($request)->key)->toBe('ip:203.0.113.7');

    $user = User::factory()->create();
    $request->setUserResolver(fn () => $user);

    expect($limiter($request)->key)->toBe('user:'.$user->id);
});
