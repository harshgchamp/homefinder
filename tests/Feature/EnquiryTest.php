<?php

use App\Events\EnquiryReceived;
use App\Listeners\LogEnquiry;
use App\Listeners\RecordLastEnquiryOnProperty;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Psr\Log\LoggerInterface;

// --- Public page --------------------------------------------------------------

test('a published property is visible to guests by its slug', function () {
    $property = Property::factory()->published()->create();

    $this->get(route('properties.show', $property->slug))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/PropertyShow')
            ->where('property.id', $property->id)
            ->where('enquiredRecently', false));
});

test('drafts, scheduled and trashed properties return 404', function (Property $property) {
    $this->get(route('properties.show', $property->slug))->assertNotFound();
})->with([
    // Pest calls each closure inside the test (after the database is reset)
    // and passes the result in as $property.
    'draft' => fn () => Property::factory()->draft()->create(),
    'scheduled' => fn () => Property::factory()->scheduled()->create(),
    'trashed' => fn () => tap(Property::factory()->published()->create())->delete(),
]);

// --- Sending an enquiry: the event is dispatched -----------------------------

test('sending an enquiry saves it and dispatches EnquiryReceived', function () {
    // Event::fake(): record dispatched events instead of running listeners,
    // so this test checks only "was the event fired, with the right data?".
    Event::fake([EnquiryReceived::class]);

    $user = User::factory()->create();
    $property = Property::factory()->published()->create();

    $this->actingAs($user)
        ->post(route('properties.enquiries.store', $property->slug), [
            'message' => 'Is a viewing possible this Saturday?',
            'phone' => '+44 7700 900123',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $enquiry = Enquiry::sole();

    expect($enquiry->user_id)->toBe($user->id)
        ->and($enquiry->property_id)->toBe($property->id);

    Event::assertDispatched(
        EnquiryReceived::class,
        fn (EnquiryReceived $event) => $event->enquiry->is($enquiry),
    );
    Event::assertDispatchedTimes(EnquiryReceived::class, 1);
});

test('without faking, both listeners run end to end', function () {
    $property = Property::factory()->published()->create(['last_enquiry_at' => null]);

    $this->actingAs(User::factory()->create())
        ->post(route('properties.enquiries.store', $property->slug), [
            'message' => 'Is a viewing possible this Saturday?',
        ]);

    expect($property->fresh()->last_enquiry_at)->not->toBeNull();
});

test('guests are sent to login and nothing is dispatched', function () {
    Event::fake([EnquiryReceived::class]);
    $property = Property::factory()->published()->create();

    $this->post(route('properties.enquiries.store', $property->slug), ['message' => 'Hello there, is it free?'])
        ->assertRedirect(route('login'));

    Event::assertNotDispatched(EnquiryReceived::class);
});

test('unverified users cannot send enquiries', function () {
    $property = Property::factory()->published()->create();

    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('properties.enquiries.store', $property->slug), ['message' => 'Hello there, is it free?'])
        ->assertRedirect(route('verification.notice'));

    expect(Enquiry::count())->toBe(0);
});

test('validation rejects a short message and a bad phone number', function () {
    $property = Property::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('properties.enquiries.store', $property->slug), [
            'message' => 'Hi',
            'phone' => 'call me maybe',
        ])
        ->assertSessionHasErrors(['message', 'phone']);
});

test('a second enquiry about the same property within 24 hours is rejected', function () {
    Event::fake([EnquiryReceived::class]);
    $user = User::factory()->create();
    $property = Property::factory()->published()->create();
    Enquiry::factory()->for($user)->for($property)->create(['created_at' => now()->subHours(2)]);

    $this->actingAs($user)
        ->post(route('properties.enquiries.store', $property->slug), ['message' => 'Following up on my question.'])
        ->assertSessionHasErrors('message');

    expect(Enquiry::count())->toBe(1);
    Event::assertNotDispatched(EnquiryReceived::class);

    // ...and the page shows the "already sent" state instead of the form.
    $this->get(route('properties.show', $property->slug))
        ->assertInertia(fn (Assert $page) => $page->where('enquiredRecently', true));
});

test('the event is not dispatched when the transaction rolls back', function () {
    // ShouldDispatchAfterCommit: dispatching inside a transaction that then
    // fails must not run any listener.
    $property = Property::factory()->published()->create(['last_enquiry_at' => null]);
    $enquiry = Enquiry::factory()->for($property)->create();

    try {
        DB::transaction(function () use ($enquiry): void {
            EnquiryReceived::dispatch($enquiry);

            throw new RuntimeException('Something failed after dispatching.');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($property->fresh()->last_enquiry_at)->toBeNull();
});

// --- Each listener on its own ---------------------------------------------------
// A listener is a normal class: create the event by hand and call handle().

test('RecordLastEnquiryOnProperty sets last_enquiry_at', function () {
    $property = Property::factory()->published()->create(['last_enquiry_at' => null]);
    $enquiry = Enquiry::factory()->for($property)->create(['created_at' => now()->subMinute()]);

    (new RecordLastEnquiryOnProperty)->handle(new EnquiryReceived($enquiry));

    expect($property->fresh()->last_enquiry_at->toDateTimeString())
        ->toBe($enquiry->created_at->toDateTimeString());
});

test('RecordLastEnquiryOnProperty never moves the date backwards and leaves updated_at alone', function () {
    $property = Property::factory()->published()->create([
        'last_enquiry_at' => now()->subHour(),
        'updated_at' => now()->subYear(),
    ]);
    $older = Enquiry::factory()->for($property)->create(['created_at' => now()->subDays(3)]);

    (new RecordLastEnquiryOnProperty)->handle(new EnquiryReceived($older));

    $fresh = $property->fresh();
    expect($fresh->last_enquiry_at->isAfter(now()->subHours(2)))->toBeTrue()
        ->and($fresh->updated_at->isBefore(now()->subMonths(11)))->toBeTrue();
});

test('LogEnquiry writes ids, not personal data, to the enquiries channel', function () {
    $enquiry = Enquiry::factory()->create(['phone' => '07700 900123']);

    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('info')->once()->with('Enquiry received', Mockery::on(
        fn (array $context) => $context === [
            'enquiry_id' => $enquiry->id,
            'property_id' => $enquiry->property_id,
            'user_id' => $enquiry->user_id,
        ],
    ));
    Log::shouldReceive('channel')->once()->with('enquiries')->andReturn($logger);

    (new LogEnquiry)->handle(new EnquiryReceived($enquiry));
});

test('both listeners are discovered for EnquiryReceived', function () {
    // assertListening() is only available on the fake dispatcher.
    Event::fake();

    Event::assertListening(EnquiryReceived::class, RecordLastEnquiryOnProperty::class);
    Event::assertListening(EnquiryReceived::class, LogEnquiry::class);
});
