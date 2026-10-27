<?php

use App\Models\Agency;
use App\Models\Note;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// --- One table, two parents ---------------------------------------------------

test('notes on a property and on an agency share one table with different types', function () {
    $property = Property::factory()->create();
    $agency = Agency::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.properties.notes.store', $property), ['body' => 'Owner wants a quick sale'])
        ->assertRedirect();
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.notes.store', $agency), ['body' => 'Contract renews in March'])
        ->assertRedirect();

    // The morph map stores the short alias, not App\Models\Property.
    expect(DB::table('notes')->orderBy('id')->get(['notable_type', 'notable_id', 'user_id'])->toArray())
        ->toEqual([
            (object) ['notable_type' => 'property', 'notable_id' => $property->id, 'user_id' => $this->admin->id],
            (object) ['notable_type' => 'agency', 'notable_id' => $agency->id, 'user_id' => $this->admin->id],
        ]);
});

test('morphTo loads the right parent model for each note', function () {
    $property = Property::factory()->create();
    $agency = Agency::factory()->create();
    $property->notes()->create(['body' => 'On a property']);
    $agency->notes()->create(['body' => 'On an agency']);

    // with('notable'): one query per parent TYPE, not one per note.
    $parents = Note::query()->with('notable')->orderBy('id')->get()->pluck('notable');

    expect($parents[0])->toBeInstanceOf(Property::class)
        ->and($parents[0]->is($property))->toBeTrue()
        ->and($parents[1])->toBeInstanceOf(Agency::class)
        ->and($parents[1]->is($agency))->toBeTrue();
});

test('a note body is required', function () {
    $property = Property::factory()->create();

    $this->actingAs($this->admin)
        ->post(route('admin.properties.notes.store', $property), ['body' => ''])
        ->assertSessionHasErrors('body');

    expect(Note::count())->toBe(0);
});

// --- Who may write ------------------------------------------------------------

test('an agent can add a note to their own agency property only', function () {
    $agent = User::factory()->agent()->create();
    $own = Property::factory()->for($agent->agency)->create();
    $other = Property::factory()->create();

    $this->actingAs($agent)
        ->post(route('admin.properties.notes.store', $own), ['body' => 'Viewing booked Saturday'])
        ->assertRedirect();
    $this->actingAs($agent)
        ->post(route('admin.properties.notes.store', $other), ['body' => 'Not my listing'])
        ->assertForbidden();

    expect($own->notes()->count())->toBe(1)
        ->and($other->notes()->count())->toBe(0);
});

test('agents cannot add notes to an agency', function () {
    $agent = User::factory()->agent()->create();

    $this->actingAs($agent)
        ->post(route('admin.agencies.notes.store', $agent->agency), ['body' => 'Hello'])
        ->assertForbidden();
});

// --- Who may delete -----------------------------------------------------------

test('the author can delete their note, a colleague cannot, an admin can', function () {
    $agency = Agency::factory()->create();
    $author = User::factory()->agent($agency)->create();
    $colleague = User::factory()->agent($agency)->create();
    $property = Property::factory()->for($agency)->create();
    $first = $property->notes()->create(['body' => 'First', 'user_id' => $author->id]);
    $second = $property->notes()->create(['body' => 'Second', 'user_id' => $author->id]);

    $this->actingAs($colleague)->delete(route('admin.notes.destroy', $first))->assertForbidden();
    $this->actingAs($author)->delete(route('admin.notes.destroy', $first))->assertRedirect();
    $this->actingAs($this->admin)->delete(route('admin.notes.destroy', $second))->assertRedirect();

    expect(Note::count())->toBe(0);
});

// --- Clean-up (no foreign key, so the HasNotes trait does it) -----------------

test('a soft delete keeps the notes and a force delete removes them', function () {
    $property = Property::factory()->create();
    $property->notes()->create(['body' => 'Keep me while in the trash']);

    $property->delete();
    expect(Note::count())->toBe(1);

    $property->forceDelete();
    expect(Note::count())->toBe(0);
});

test('deleting an agency removes its notes but not other notes', function () {
    $agency = Agency::factory()->create();
    $agency->notes()->create(['body' => 'Goes with the agency']);
    Property::factory()->create()->notes()->create(['body' => 'Stays']);

    $agency->delete();

    expect(Note::pluck('body')->all())->toBe(['Stays']);
});

// --- Pages --------------------------------------------------------------------

test('the property page lists its notes, newest first, with delete rights', function () {
    $property = Property::factory()->create();
    // created_at is not fillable (rightly), so travel back in time to write the older note.
    $this->travelTo(now()->subDay(), fn () => $property->notes()->create(['body' => 'Older', 'user_id' => $this->admin->id]));
    $property->notes()->create(['body' => 'Newer', 'user_id' => $this->admin->id]);
    // A note on ANOTHER property must not appear (same table, different notable_id).
    Property::factory()->create()->notes()->create(['body' => 'Elsewhere']);

    $this->actingAs($this->admin)
        ->get(route('admin.properties.show', $property))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/properties/Show')
            ->has('notes', 2)
            ->where('notes.0.body', 'Newer')
            ->where('notes.0.author', $this->admin->name)
            ->where('notes.0.can_delete', true)
            ->where('notes.1.body', 'Older'));
});

test('the agency edit page lists its notes', function () {
    $agency = Agency::factory()->create();
    $agency->notes()->create(['body' => 'Contract renews in March']);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.edit', $agency))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/agencies/Edit')
            ->has('notes', 1)
            ->where('notes.0.body', 'Contract renews in March')
            // No user_id: shown as a deleted author, not an error.
            ->where('notes.0.author', 'Deleted user'));
});

// --- Morph map ----------------------------------------------------------------

test('notifications and tokens are stored with the short alias too', function () {
    $user = User::factory()->create();
    $user->createToken('phone');

    expect(DB::table('personal_access_tokens')->value('tokenable_type'))->toBe('user');
});
