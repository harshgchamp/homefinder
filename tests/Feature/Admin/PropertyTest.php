<?php

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    // Storage::fake(): a temporary disk, so tests never write real files.
    Storage::fake('public');

    $this->admin = User::factory()->admin()->create();
});

/**
 * A valid form submission; each test overrides only what it is testing.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function propertyPayload(array $overrides = []): array
{
    return [
        'agency_id' => Agency::factory()->create()->id,
        'property_type_id' => PropertyType::factory()->create()->id,
        'title' => 'Three bed house',
        'description' => 'A lovely family home close to the station and good schools.',
        'listing_type' => 'sale',
        'status' => 'available',
        'price' => 350000,
        'bedrooms' => 3,
        'bathrooms' => 1,
        'furnished' => null,
        'address_line_1' => '12 High Street',
        'city' => 'Romford',
        'postcode' => 'rm1 1aa',
        'published_at' => null,
        ...$overrides,
    ];
}

// --- Access -----------------------------------------------------------------

test('guests are redirected to login', function () {
    $this->get(route('admin.properties.index'))->assertRedirect(route('login'));
});

test('non-admin users are forbidden', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.properties.index'))
        ->assertForbidden();
});

// --- Index and filters ------------------------------------------------------

test('admin sees the property list with counts and eager loaded relations', function () {
    $property = Property::factory()->published()->create();
    Enquiry::factory()->count(2)->for($property)->create();

    $this->actingAs($this->admin)
        ->get(route('admin.properties.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/properties/Index')
            ->has('properties.data', 1)
            ->where('properties.data.0.enquiries_count', 2)
            ->where('properties.data.0.agency.name', $property->agency->name)
            // description was not selected on the list, so whenHas() left it out
            ->missing('properties.data.0.description')
            ->has('options.cities'));
});

test('filters by city, listing type and price range', function () {
    Property::factory()->forSale()->create(['city' => 'Romford', 'price' => 300000]);
    Property::factory()->forSale()->create(['city' => 'Romford', 'price' => 900000]);
    Property::factory()->forRent()->create(['city' => 'Romford']);
    Property::factory()->forSale()->create(['city' => 'Ilford', 'price' => 300000]);

    $this->actingAs($this->admin)
        ->get(route('admin.properties.index', [
            'city' => 'Romford',
            'listing_type' => 'sale',
            'max_price' => 500000,
        ]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.price', 300000)
            ->where('filters.city', 'Romford'));
});

test('feature filter requires ALL selected features', function () {
    [$garden, $parking] = Feature::factory()->count(2)->create();

    $both = Property::factory()->create();
    $both->features()->attach([$garden->id, $parking->id]);

    $gardenOnly = Property::factory()->create();
    $gardenOnly->features()->attach($garden->id);

    $this->actingAs($this->admin)
        ->get(route('admin.properties.index', ['features' => [$garden->id, $parking->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.id', $both->id));
});

test('filters by publication status', function (string $publication, string $state) {
    Property::factory()->draft()->create();
    Property::factory()->scheduled()->create();
    Property::factory()->published()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.properties.index', ['publication' => $publication]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.publication.value', $state));
})->with([
    'drafts' => ['draft', 'draft'],
    'scheduled' => ['scheduled', 'scheduled'],
    'published' => ['published', 'published'],
]);

test('trashed filter shows only soft deleted properties', function () {
    Property::factory()->create();
    Property::factory()->create()->delete();

    $this->actingAs($this->admin)
        ->get(route('admin.properties.index', ['trashed' => 'only']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('properties.data', 1)
            ->where('properties.data.0.is_trashed', true));
});

test('an invalid filter redirects to the unfiltered list', function () {
    $this->actingAs($this->admin)
        ->get(route('admin.properties.index', ['sort' => 'password']))
        ->assertRedirect(route('admin.properties.index'))
        ->assertSessionHasErrors('sort');
});

// --- Create -----------------------------------------------------------------

test('admin can create a property with features and images', function () {
    $features = Feature::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)->post(route('admin.properties.store'), propertyPayload([
        'features' => $features->pluck('id')->all(),
        'images' => [
            UploadedFile::fake()->image('front.jpg'),
            UploadedFile::fake()->image('garden.jpg'),
        ],
    ]));

    $property = Property::sole();

    $response->assertRedirect(route('admin.properties.show', $property));

    expect($property->slug)->toBe('three-bed-house')        // observer
        ->and($property->postcode)->toBe('RM1 1AA')          // mutator
        ->and($property->features)->toHaveCount(2)           // sync()
        ->and($property->images)->toHaveCount(2)
        ->and($property->images->pluck('sort_order')->all())->toBe([0, 1]);

    $property->images->each(fn (PropertyImage $image) => Storage::disk('public')->assertExists($image->path));
});

test('status must be allowed for the listing type', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.properties.store'), propertyPayload([
            'listing_type' => 'rent',
            'status' => 'sold',
        ]))
        ->assertSessionHasErrors('status');

    expect(Property::count())->toBe(0);
});

test('slug skips one already used by a trashed property', function () {
    Property::factory()->create(['slug' => 'three-bed-house'])->delete();

    $this->actingAs($this->admin)->post(route('admin.properties.store'), propertyPayload());

    expect(Property::latest('id')->first()->slug)->toBe('three-bed-house-2');
});

// --- Update -----------------------------------------------------------------

test('update syncs features, removes and adds images, and keeps the slug', function () {
    [$keep, $drop, $add] = Feature::factory()->count(3)->create();

    $property = Property::factory()->create(['slug' => 'original-slug']);
    $property->features()->attach([$keep->id, $drop->id]);

    Storage::disk('public')->put('properties/old.jpg', 'x');
    $oldImage = $property->images()->create(['path' => 'properties/old.jpg', 'sort_order' => 0]);

    $this->actingAs($this->admin)
        ->put(route('admin.properties.update', $property), propertyPayload([
            'agency_id' => $property->agency_id,
            'title' => 'A completely new title',
            'features' => [$keep->id, $add->id],
            'remove_images' => [$oldImage->id],
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ]))
        ->assertRedirect(route('admin.properties.show', $property));

    $property->refresh();

    expect($property->title)->toBe('A completely new title')
        ->and($property->slug)->toBe('original-slug')
        ->and($property->features->pluck('id')->sort()->values()->all())->toBe([$keep->id, $add->id])
        ->and($property->images)->toHaveCount(1)
        ->and(PropertyImage::find($oldImage->id))->toBeNull();

    // The observer deleted the file of the removed image.
    Storage::disk('public')->assertMissing('properties/old.jpg');
});

test('cannot remove an image that belongs to another property', function () {
    $property = Property::factory()->create();
    $otherImage = PropertyImage::factory()->create();

    $this->actingAs($this->admin)
        ->put(route('admin.properties.update', $property), propertyPayload([
            'agency_id' => $property->agency_id,
            'remove_images' => [$otherImage->id],
        ]))
        ->assertSessionHasErrors('remove_images.0');

    expect(PropertyImage::find($otherImage->id))->not->toBeNull();
});

// --- Delete, restore, force delete ------------------------------------------

test('destroy soft deletes, show still works, and restore brings it back', function () {
    $property = Property::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.properties.destroy', $property));
    expect($property->fresh()->trashed())->toBeTrue();

    // withTrashed(['show']) on the resource route
    $this->get(route('admin.properties.show', $property))->assertOk();

    $this->patch(route('admin.properties.restore', $property));
    expect($property->fresh()->trashed())->toBeFalse();
});

test('force delete only works from the trash and removes image files', function () {
    $property = Property::factory()->create();
    Storage::disk('public')->put('properties/a.jpg', 'x');
    $property->images()->create(['path' => 'properties/a.jpg']);

    $this->actingAs($this->admin)
        ->delete(route('admin.properties.force-delete', $property))
        ->assertStatus(409);

    $property->delete();

    $this->delete(route('admin.properties.force-delete', $property))
        ->assertRedirect(route('admin.properties.index', ['trashed' => 'only']));

    expect(Property::withTrashed()->find($property->id))->toBeNull();
    Storage::disk('public')->assertMissing('properties/a.jpg');
});

test('force delete is refused when the property has enquiries', function () {
    $property = Property::factory()->create();
    Enquiry::factory()->for($property)->create();
    Storage::disk('public')->put('properties/a.jpg', 'x');
    $property->images()->create(['path' => 'properties/a.jpg']);
    $property->delete();

    $this->actingAs($this->admin)
        ->from(route('admin.properties.index'))
        ->delete(route('admin.properties.force-delete', $property))
        ->assertRedirect(route('admin.properties.index'));

    // The transaction rolled back: the property, its image row and the file all survive.
    expect(Property::withTrashed()->find($property->id))->not->toBeNull()
        ->and($property->images()->count())->toBe(1);
    Storage::disk('public')->assertExists('properties/a.jpg');
});

// --- Bulk and export --------------------------------------------------------

test('bulk publish only publishes drafts and scheduled properties', function () {
    $draft = Property::factory()->draft()->create();
    $published = Property::factory()->create(['published_at' => now()->subYear()]);

    $this->actingAs($this->admin)
        ->post(route('admin.properties.bulk'), [
            'action' => 'publish',
            'ids' => [$draft->id, $published->id],
        ]);

    expect($draft->fresh()->published_at)->not->toBeNull()
        // The year-old date was kept, not reset to now.
        ->and($published->fresh()->published_at->isBefore(now()->subMonths(11)))->toBeTrue();
});

test('export streams a filtered CSV and neutralises formulas', function () {
    Property::factory()->create(['city' => 'Romford', 'title' => '=HYPERLINK("http://evil")']);
    Property::factory()->create(['city' => 'Ilford', 'title' => 'Not in the export']);

    $response = $this->actingAs($this->admin)
        ->get(route('admin.properties.export', ['city' => 'Romford']));

    $response->assertOk();
    $csv = $response->streamedContent();

    expect($csv)->toContain('Romford')
        ->toContain("'=HYPERLINK")
        ->not->toContain('Not in the export');
});

// --- Pruning ----------------------------------------------------------------

test('model:prune permanently deletes properties trashed over 30 days ago', function () {
    $old = Property::factory()->create();
    Storage::disk('public')->put('properties/old.jpg', 'x');
    $old->images()->create(['path' => 'properties/old.jpg']);
    $old->delete();
    $old->forceFill(['deleted_at' => now()->subDays(31)])->saveQuietly();

    $recent = Property::factory()->create();
    $recent->delete();

    $this->artisan('model:prune', ['--model' => [Property::class]])->assertSuccessful();

    expect(Property::withTrashed()->find($old->id))->toBeNull()
        ->and(Property::withTrashed()->find($recent->id))->not->toBeNull();
    Storage::disk('public')->assertMissing('properties/old.jpg');
});

test('formatted price and publication status accessors', function () {
    $rent = Property::factory()->forRent()->make(['price' => 1200, 'published_at' => null]);
    $sale = Property::factory()->forSale()->make(['price' => 250000, 'published_at' => now()->addDay()]);

    expect($rent->formatted_price)->toBe('£1,200 pcm')
        ->and($rent->publication_status->value)->toBe('draft')
        ->and($sale->formatted_price)->toBe('£250,000')
        ->and($sale->publication_status->value)->toBe('scheduled')
        ->and(PropertyStatus::Sold->isAllowedFor(ListingType::Rent))->toBeFalse();
});
