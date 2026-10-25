<?php

use App\Models\Agency;
use App\Models\Property;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// --- Access -----------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get(route('admin.agencies.index'))->assertRedirect(route('login'));
});

test('normal users are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.agencies.index'))
        ->assertForbidden();
});

test('agents are forbidden, agencies are managed by admins only', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->get(route('admin.agencies.index'))
        ->assertForbidden();
});

// --- List -------------------------------------------------------------------

test('admins see the agency list with property counts', function () {
    $agency = Agency::factory()->create(['name' => 'Acme Homes']);
    Property::factory()->count(2)->for($agency)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/agencies/Index')
            ->has('agencies.data', 1)
            ->where('agencies.data.0.name', 'Acme Homes')
            ->where('agencies.data.0.properties_count', 2)
            ->where('filters', ['search' => null, 'status' => null, 'sort' => 'name'])
        );
});

test('search matches the name or the email', function () {
    Agency::factory()->create(['name' => 'London Lettings', 'email' => 'hello@ll.test']);
    Agency::factory()->create(['name' => 'Kent Homes', 'email' => 'info@london-agents.test']);
    Agency::factory()->create(['name' => 'Essex Estates', 'email' => 'team@essex.test']);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index', ['search' => 'london']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agencies.data', 2)
            ->where('filters.search', 'london')
        );
});

test('the status filter shows only active or inactive agencies', function () {
    Agency::factory()->count(2)->create(['is_active' => true]);
    Agency::factory()->create(['is_active' => false, 'name' => 'Closed Agency']);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index', ['status' => 'inactive']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agencies.data', 1)
            ->where('agencies.data.0.name', 'Closed Agency')
        );
});

test('agencies can be sorted by most properties', function () {
    $small = Agency::factory()->create(['name' => 'A Small']);
    $big = Agency::factory()->create(['name' => 'B Big']);
    Property::factory()->for($small)->create();
    Property::factory()->count(3)->for($big)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index', ['sort' => 'most_properties']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('agencies.data.0.name', 'B Big')
            ->where('agencies.data.1.name', 'A Small')
        );
});

test('the list is paginated and the filters stay in the page links', function () {
    Agency::factory()->count(12)->create(['is_active' => true]);

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index', ['status' => 'active']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('agencies.data', 10)
            ->where('agencies.meta.total', 12)
            ->where('agencies.meta.last_page', 2)
            // withQueryString(): page 2's link must still carry ?status=active.
            ->where('agencies.meta.links.2.url', fn (string $url) => str_contains($url, 'status=active') && str_contains($url, 'page=2'))
        );
});

test('an invalid sort is rejected', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.agencies.index', ['sort' => 'password']))
        ->assertRedirect(route('admin.agencies.index'))
        ->assertSessionHasErrors('sort');
});

// --- Create -----------------------------------------------------------------

/**
 * A valid form submission; each test overrides only what it is testing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agencyPayload(array $overrides = []): array
{
    return [
        'name' => 'Acme Homes',
        'email' => 'hello@acme-homes.test',
        'phone' => '020 7946 0000',
        'is_active' => true,
        ...$overrides,
    ];
}

test('admins can open the create page', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.agencies.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/agencies/Create'));
});

test('admins can create an agency and the slug is generated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), agencyPayload([
            'name' => '  Acme   Homes ',
            'email' => ' Hello@Acme-Homes.TEST ',
        ]))
        ->assertRedirect(route('admin.agencies.index'))
        ->assertSessionHasNoErrors();

    $agency = Agency::sole();

    // prepareForValidation() cleaned the input before it was stored.
    expect($agency->name)->toBe('Acme Homes')
        ->and($agency->email)->toBe('hello@acme-homes.test')
        ->and($agency->slug)->toBe('acme-homes')
        ->and($agency->is_active)->toBeTrue();
});

test('a duplicate slug gets a number added', function () {
    Agency::factory()->create(['name' => 'Acme Homes', 'slug' => 'acme-homes']);

    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), agencyPayload(['name' => 'Acme-Homes', 'email' => 'other@acme.test']))
        ->assertSessionHasNoErrors();

    expect(Agency::where('name', 'Acme-Homes')->value('slug'))->toBe('acme-homes-2');
});

test('creating validates the input', function (array $overrides, string $field) {
    Agency::factory()->create(['name' => 'Taken Name', 'email' => 'taken@agency.test']);

    $this->actingAs($this->admin)
        ->post(route('admin.agencies.store'), agencyPayload($overrides))
        ->assertSessionHasErrors($field);

    expect(Agency::count())->toBe(1);
})->with([
    'name is required' => [['name' => ''], 'name'],
    'name is unique' => [['name' => 'Taken Name'], 'name'],
    'email is required' => [['email' => ''], 'email'],
    'email must be valid' => [['email' => 'not-an-email'], 'email'],
    'email is unique (any case)' => [['email' => 'TAKEN@agency.test'], 'email'],
    'phone is required' => [['phone' => ''], 'phone'],
    'phone has a valid format' => [['phone' => 'call me'], 'phone'],
]);

test('agents cannot create agencies', function () {
    $this->actingAs(User::factory()->agent()->create())
        ->post(route('admin.agencies.store'), agencyPayload())
        ->assertForbidden();

    expect(Agency::where('name', 'Acme Homes')->exists())->toBeFalse();
});

// --- Update -----------------------------------------------------------------

test('admins can open the edit page', function () {
    $agency = Agency::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.agencies.edit', $agency))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/agencies/Edit')
            ->where('agency.id', $agency->id)
        );
});

test('admins can update an agency and the slug follows a new name', function () {
    $agency = Agency::factory()->create(['name' => 'Old Name', 'slug' => 'old-name']);

    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency), agencyPayload([
            'name' => 'New Name',
            'is_active' => false,
        ]))
        ->assertRedirect(route('admin.agencies.index'))
        ->assertSessionHasNoErrors();

    expect($agency->fresh())
        ->name->toBe('New Name')
        ->slug->toBe('new-name')
        ->is_active->toBeFalse();
});

test('saving an agency without changing its name or email passes the unique rules', function () {
    $agency = Agency::factory()->create(agencyPayload());

    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency), agencyPayload(['phone' => '0113 496 0000']))
        ->assertSessionHasNoErrors();

    expect($agency->fresh()->phone)->toBe('0113 496 0000');
});

test('an agency cannot take another agency\'s email', function () {
    Agency::factory()->create(['email' => 'taken@agency.test']);
    $agency = Agency::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.agencies.update', $agency), agencyPayload(['email' => 'taken@agency.test']))
        ->assertSessionHasErrors('email');
});

// --- Delete -----------------------------------------------------------------

test('admins can delete an agency without properties and its agents are kept', function () {
    $agency = Agency::factory()->create();
    $agent = User::factory()->agent($agency)->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertRedirect(route('admin.agencies.index'));

    expect(Agency::find($agency->id))->toBeNull()
        // users.agency_id is nullOnDelete: the agent becomes a normal user.
        ->and($agent->fresh())->not->toBeNull()
        ->and($agent->fresh()->agency_id)->toBeNull();
});

test('an agency with properties cannot be deleted', function () {
    $agency = Agency::factory()->create();
    Property::factory()->for($agency)->create();

    $this->actingAs($this->admin)
        ->from(route('admin.agencies.index'))
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertRedirect(route('admin.agencies.index'));

    expect(Agency::find($agency->id))->not->toBeNull();
});

test('trashed properties also block deleting an agency', function () {
    $agency = Agency::factory()->create();
    Property::factory()->for($agency)->create()->delete(); // soft delete

    $this->actingAs($this->admin)
        ->delete(route('admin.agencies.destroy', $agency));

    // Without withTrashed() in the check this would be a foreign key error (500).
    expect(Agency::find($agency->id))->not->toBeNull();
});

test('agents cannot delete agencies', function () {
    $agency = Agency::factory()->create();

    $this->actingAs(User::factory()->agent()->create())
        ->delete(route('admin.agencies.destroy', $agency))
        ->assertForbidden();

    expect(Agency::find($agency->id))->not->toBeNull();
});
