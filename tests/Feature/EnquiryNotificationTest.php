<?php

use App\Events\EnquiryReceived;
use App\Listeners\SendEnquiryNotifications;
use App\Mail\EnquirySent;
use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Notifications\NewEnquiryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agency = Agency::factory()->create(['email' => 'lettings@agency.test']);
    $this->property = Property::factory()->published()->for($this->agency)->create();
    $this->enquirer = User::factory()->create(['name' => 'Priya Shah', 'email' => 'priya@example.test']);
    $this->enquiry = Enquiry::factory()->for($this->property)->for($this->enquirer)->create([
        'message' => 'Could I view it on Saturday morning?',
        'phone' => '07700 900123',
    ]);
});

// --- Who gets what ------------------------------------------------------------

test('the agency is emailed, admins get a database notification, the enquirer gets a confirmation', function () {
    // fake(): record what WOULD be sent, send nothing.
    Notification::fake();
    Mail::fake();

    $admin = User::factory()->admin()->create();
    $otherUser = User::factory()->create();

    (new SendEnquiryNotifications)->handle(new EnquiryReceived($this->enquiry));

    // The second argument of the closure is the list of channels used for that recipient.
    Notification::assertSentTo(
        $this->agency,
        NewEnquiryNotification::class,
        fn (NewEnquiryNotification $n, array $channels) => $channels === ['mail'] && $n->enquiry->is($this->enquiry),
    );
    Notification::assertSentTo(
        $admin,
        NewEnquiryNotification::class,
        fn (NewEnquiryNotification $n, array $channels) => $channels === ['database'],
    );
    Notification::assertNotSentTo($otherUser, NewEnquiryNotification::class);
    Notification::assertNotSentTo($this->enquirer, NewEnquiryNotification::class);

    // assertQueued, not assertSent: EnquirySent implements ShouldQueue.
    Mail::assertQueued(EnquirySent::class, fn (EnquirySent $mail) => $mail->hasTo('priya@example.test'));
});

test('sending the enquiry form triggers the notifications end to end', function () {
    Notification::fake();
    Mail::fake();
    $property = Property::factory()->published()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('properties.enquiries.store', $property->slug), ['message' => 'Is the garden south facing?']);

    Notification::assertSentTo($property->agency, NewEnquiryNotification::class);
    Mail::assertQueued(EnquirySent::class);
});

test('both messages are queued, not sent during the request', function () {
    expect(new NewEnquiryNotification($this->enquiry))->toBeInstanceOf(ShouldQueue::class)
        ->and(new EnquirySent($this->enquiry))->toBeInstanceOf(ShouldQueue::class);
});

// --- Content ----------------------------------------------------------------------

test('the agency email has the details and replies go to the enquirer', function () {
    $mail = (new NewEnquiryNotification($this->enquiry))->toMail($this->agency);

    expect($mail->subject)->toBe("New enquiry: {$this->property->title}")
        ->and($mail->replyTo)->toBe([['priya@example.test', 'Priya Shah']])
        ->and($mail->greeting)->toBe("Hello {$this->agency->name},")
        ->and(implode("\n", $mail->introLines))
        ->toContain('Could I view it on Saturday morning?')
        ->toContain('Phone: 07700 900123')
        ->and($mail->actionUrl)->toBe(route('properties.show', $this->property->slug));
});

test('the phone line is left out when no phone was given', function () {
    $this->enquiry->update(['phone' => null]);

    $mail = (new NewEnquiryNotification($this->enquiry))->toMail($this->agency);

    expect(implode("\n", $mail->introLines))->not->toContain('Phone:');
});

test('the database notification stores a small summary with a stable type', function () {
    $admin = User::factory()->admin()->create();

    // Not faked: the test queue is "sync", so the queued notification runs now.
    $admin->notify(new NewEnquiryNotification($this->enquiry));

    $row = $admin->notifications()->sole();
    expect($row->type)->toBe('enquiry.received')
        ->and($row->read_at)->toBeNull()
        ->and($row->data)->toBe([
            'enquiry_id' => $this->enquiry->id,
            'property_id' => $this->property->id,
            'property_title' => $this->property->title,
            'from' => 'Priya Shah',
            'excerpt' => 'Could I view it on Saturday morning?',
        ]);
});

test('the confirmation email renders the property and the message', function () {
    $mail = new EnquirySent($this->enquiry);

    $mail->assertHasSubject("Your enquiry about {$this->property->title}");
    $mail->assertSeeInHtml($this->property->title);
    $mail->assertSeeInHtml($this->agency->name);
    $mail->assertSeeInText('Could I view it on Saturday morning?');
});

// --- Admin notifications page ---------------------------------------------------

test('admins see their notifications and the unread count on every page', function () {
    $admin = User::factory()->admin()->create();
    $admin->notify(new NewEnquiryNotification($this->enquiry));

    $this->actingAs($admin)
        ->get(route('admin.notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/notifications/Index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.data.from', 'Priya Shah')
            ->where('notifications.data.0.read', false)
            ->where('unreadNotificationsCount', 1));
});

test('opening a notification marks it read and goes to the property', function () {
    $admin = User::factory()->admin()->create();
    $admin->notify(new NewEnquiryNotification($this->enquiry));
    $id = $admin->notifications()->value('id');

    $this->actingAs($admin)
        ->post(route('admin.notifications.read', $id))
        ->assertRedirect(route('admin.properties.show', $this->property->id));

    expect($admin->unreadNotifications()->count())->toBe(0);
});

test('an admin cannot open another admin\'s notification', function () {
    $owner = User::factory()->admin()->create();
    $owner->notify(new NewEnquiryNotification($this->enquiry));
    $id = $owner->notifications()->value('id');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.notifications.read', $id))
        ->assertNotFound();

    expect($owner->unreadNotifications()->count())->toBe(1);
});

test('mark all as read clears the unread count', function () {
    $admin = User::factory()->admin()->create();
    $admin->notify(new NewEnquiryNotification($this->enquiry));
    $admin->notify(new NewEnquiryNotification($this->enquiry));

    $this->actingAs($admin)->post(route('admin.notifications.read-all'))->assertRedirect();

    expect($admin->unreadNotifications()->count())->toBe(0);
});

test('non-admins cannot see the notifications page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.notifications.index'))
        ->assertForbidden();
});

test('the third listener is discovered', function () {
    Event::fake();

    Event::assertListening(EnquiryReceived::class, SendEnquiryNotifications::class);
});
