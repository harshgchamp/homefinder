<?php

use App\Models\Property;
use App\Models\PropertyType;
use App\Models\User;
use App\Support\CachedLookups;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
});

// --- Access -----------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get(route('admin.property-types.index'))->assertRedirect(route('login'));
});

test('agents and normal users are forbidden', function (string $role) {
    $user = $role === 'agent' ? User::factory()->agent()->create() : User::factory()->create();

    $this->actingAs($user)->get(route('admin.property-types.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.property-types.store'), ['name' => 'Castle'])->assertForbidden();
})->with(['agent', 'user']);

// --- List -------------------------------------------------------------------

test('the list shows types with property counts and can be searched', function () {
    $flat = PropertyType::factory()->create(['name' => 'Flat']);
    PropertyType::factory()->create(['name' => 'Bungalow']);
    Property::factory()->count(2)->for($flat)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.property-types.index', ['search' => 'fla']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/property-types/Index')
            ->has('types.data', 1)
            ->where('types.data.0.name', 'Flat')
            ->where('types.data.0.properties_count', 2)
            ->where('filters.search', 'fla'));
});

// --- One form, two modes -------------------------------------------------------

test('the create page sends no type, so the shared form is in create mode', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.property-types.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/property-types/Create')
            ->missing('type'));
});

test('the edit page sends the type, so the shared form is in edit mode', function () {
    $type = PropertyType::factory()->create(['name' => 'Flat']);

    $this->actingAs($this->admin)
        ->get(route('admin.property-types.edit', $type))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/property-types/Edit')
            ->where('type.id', $type->id)
            ->where('type.name', 'Flat')
            ->where('type.properties_count', 0));
});

// --- Create -----------------------------------------------------------------

test('an admin can create a type, with the name cleaned and a slug generated', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.property-types.store'), ['name' => '  semi   detached '])
        ->assertRedirect(route('admin.property-types.index'));

    $type = PropertyType::sole();
    expect($type->name)->toBe('Semi detached')
        ->and($type->slug)->toBe('semi-detached');
});

test('names must be unique and present', function () {
    PropertyType::factory()->create(['name' => 'Flat']);

    $this->actingAs($this->admin)
        ->post(route('admin.property-types.store'), ['name' => 'flat'])
        ->assertSessionHasErrors(['name' => 'A property type with this name already exists.']);

    $this->actingAs($this->admin)
        ->post(route('admin.property-types.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

// --- Update -----------------------------------------------------------------

test('an admin can rename a type, and the slug follows', function () {
    $type = PropertyType::factory()->create(['name' => 'Flat', 'slug' => 'flat']);

    $this->actingAs($this->admin)
        ->put(route('admin.property-types.update', $type), ['name' => 'Apartment'])
        ->assertRedirect(route('admin.property-types.index'));

    expect($type->fresh())
        ->name->toBe('Apartment')
        ->slug->toBe('apartment');
});

test('saving a type with its own unchanged name is allowed', function () {
    $type = PropertyType::factory()->create(['name' => 'Flat']);

    $this->actingAs($this->admin)
        ->put(route('admin.property-types.update', $type), ['name' => 'Flat'])
        ->assertSessionHasNoErrors();
});

// --- Delete -----------------------------------------------------------------

test('an unused type can be deleted', function () {
    $type = PropertyType::factory()->create();

    $this->actingAs($this->admin)
        ->delete(route('admin.property-types.destroy', $type))
        ->assertRedirect(route('admin.property-types.index'));

    expect(PropertyType::count())->toBe(0);
});

test('a type used by a property, even a trashed one, is not deleted', function () {
    $type = PropertyType::factory()->create();
    Property::factory()->for($type)->create()->delete();

    $this->actingAs($this->admin)
        ->from(route('admin.property-types.index'))
        ->delete(route('admin.property-types.destroy', $type))
        ->assertRedirect(route('admin.property-types.index'));

    expect($type->fresh())->not->toBeNull();
});

// --- Cache ------------------------------------------------------------------

test('creating a type clears the cached dropdown list', function () {
    CachedLookups::propertyTypes();
    expect(Cache::has(CachedLookups::PROPERTY_TYPES))->toBeTrue();

    $this->actingAs($this->admin)->post(route('admin.property-types.store'), ['name' => 'Cottage']);

    expect(Cache::has(CachedLookups::PROPERTY_TYPES))->toBeFalse();
});
