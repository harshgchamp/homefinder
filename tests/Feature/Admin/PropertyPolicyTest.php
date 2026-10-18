<?php

use App\Models\Agency;
use App\Models\Property;
use App\Models\User;
use App\Policies\PropertyPolicy;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->agency = Agency::factory()->create();
    $this->otherAgency = Agency::factory()->create();

    $this->agent = User::factory()->agent($this->agency)->create();
    $this->admin = User::factory()->admin()->create();
    $this->user = User::factory()->create();

    $this->own = Property::factory()->for($this->agency)->create(['city' => 'Romford']);
    $this->others = Property::factory()->for($this->otherAgency)->create(['city' => 'Ilford']);
});

// --- The policy on its own --------------------------------------------------------

test('admins may do everything, through before()', function () {
    foreach (['view', 'update', 'delete', 'restore', 'forceDelete'] as $ability) {
        expect($this->admin->can($ability, $this->others))->toBeTrue();
    }
    expect($this->admin->can('create', Property::class))->toBeTrue();
});

test('agents manage their own agency\'s listings but never delete forever', function () {
    foreach (['view', 'update', 'delete', 'restore'] as $ability) {
        expect($this->agent->can($ability, $this->own))->toBeTrue("own: {$ability}")
            ->and($this->agent->can($ability, $this->others))->toBeFalse("others: {$ability}");
    }

    expect($this->agent->can('forceDelete', $this->own))->toBeFalse()
        ->and($this->agent->can('create', Property::class))->toBeTrue();
});

test('normal users may do nothing', function () {
    expect($this->user->can('viewAny', Property::class))->toBeFalse()
        ->and($this->user->can('create', Property::class))->toBeFalse()
        ->and($this->user->can('view', $this->own))->toBeFalse();
});

test('Laravel finds the policy by naming convention', function () {
    expect(Gate::getPolicyFor(Property::class))->toBeInstanceOf(PropertyPolicy::class);
});

// --- Admin area access (gates) --------------------------------------------------------

test('agents can enter the admin area, normal users cannot', function () {
    $this->actingAs($this->agent)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($this->user)->get(route('admin.dashboard'))->assertForbidden();
});

test('features and notifications are for admins only', function () {
    $this->actingAs($this->agent)->get(route('admin.features.index'))->assertForbidden();
    $this->actingAs($this->agent)->get(route('admin.notifications.index'))->assertForbidden();

    $this->actingAs($this->admin)->get(route('admin.features.index'))->assertOk();
});

test('the sidebar admin link follows the access-admin gate', function () {
    $this->actingAs($this->agent)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdmin', true));
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.canAccessAdmin', false));
});

// --- Lists are scoped (visibleTo) -------------------------------------------------------

test('an agent\'s property list only shows their agency', function () {
    $this->actingAs($this->agent)
        ->get(route('admin.properties.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $this->own->id)
            // Permissions for the buttons, from the policy
            ->where('properties.data.0.can.update', true)
            ->where('properties.data.0.can.forceDelete', false)
            // Filter options are scoped too
            ->where('options.cities', ['Romford'])
            ->has('options.agencies', 1));
});

test('an admin sees every agency', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.properties.index'))
        ->assertInertia(fn (Assert $page) => $page->has('properties.data', 2));
});

test('the dashboard counts only the agent\'s listings', function () {
    Property::factory()->for($this->agency)->draft()->create();

    $this->actingAs($this->agent)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.drafts', 2));
});

test('the CSV export only contains the agent\'s listings', function () {
    $csv = $this->actingAs($this->agent)->get(route('admin.properties.export'))->streamedContent();

    expect($csv)->toContain($this->own->title.'')
        ->and(substr_count($csv, "\n"))->toBe(2); // header + one row
});

// --- Single-model actions (Gate::authorize -> 403) ----------------------------------------

test('an agent cannot open, edit or update another agency\'s listing', function () {
    $this->actingAs($this->agent);

    $this->get(route('admin.properties.show', $this->others))->assertForbidden();
    $this->get(route('admin.properties.edit', $this->others))->assertForbidden();
    $this->put(route('admin.properties.update', $this->others), ['title' => 'Hijacked'])->assertForbidden();
    $this->delete(route('admin.properties.destroy', $this->others))->assertForbidden();

    expect($this->others->fresh()->title)->not->toBe('Hijacked')
        ->and($this->others->fresh()->trashed())->toBeFalse();
});

test('an agent can trash and restore their own listing but not delete it forever', function () {
    $this->actingAs($this->agent);

    $this->delete(route('admin.properties.destroy', $this->own))->assertRedirect();
    $this->patch(route('admin.properties.restore', $this->own))->assertRedirect();

    $this->own->delete();
    $this->delete(route('admin.properties.force-delete', $this->own))->assertForbidden();
    expect(Property::withTrashed()->find($this->own->id))->not->toBeNull();
});

test('an agent can only create listings for their own agency', function (string $whose, bool $allowed) {
    $agencyId = $whose === 'own' ? $this->agency->id : $this->otherAgency->id;

    $response = $this->actingAs($this->agent)->post(route('admin.properties.store'), [
        'agency_id' => $agencyId,
        'property_type_id' => $this->own->property_type_id,
        'title' => 'Two bed flat',
        'description' => 'Bright flat near the station with a balcony.',
        'listing_type' => 'sale',
        'status' => 'available',
        'price' => 250000,
        'bedrooms' => 2,
        'bathrooms' => 1,
        'address_line_1' => '1 Station Road',
        'city' => 'Romford',
        'postcode' => 'RM1 1AA',
    ]);

    $allowed
        ? $response->assertSessionHasNoErrors()
        : $response->assertSessionHasErrors('agency_id');
})->with([
    'own agency' => ['own', true],
    'other agency' => ['other', false],
]);

test('bulk actions ignore ids from other agencies', function () {
    $this->own->update(['published_at' => null]);
    $this->others->update(['published_at' => null]);

    $this->actingAs($this->agent)->post(route('admin.properties.bulk'), [
        'action' => 'publish',
        'ids' => [$this->own->id, $this->others->id],
    ]);

    expect($this->own->fresh()->published_at)->not->toBeNull()
        ->and($this->others->fresh()->published_at)->toBeNull();
});
