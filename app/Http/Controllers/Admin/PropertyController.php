<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyIndexRequest;
use App\Http\Requests\Admin\StorePropertyRequest;
use App\Http\Requests\Admin\UpdatePropertyRequest;
use App\Http\Resources\NoteResource;
use App\Http\Resources\PropertyResource;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use App\Services\PropertyService;
use App\Support\CachedLookups;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for properties. Kept thin: validation is in the Form Requests,
 * writes are in PropertyService, the JSON shape is in PropertyResource.
 * This class only reads data for pages and turns results into redirects.
 */
class PropertyController extends Controller
{
    /**
     * Columns the list page needs. Not selecting description (up to 5,000
     * characters per row) keeps the query and the page payload small.
     * agency_id and property_type_id must be included, or eager loading
     * agency/propertyType has no key to match on.
     */
    private const LIST_COLUMNS = [
        'id', 'agency_id', 'property_type_id', 'title', 'slug', 'listing_type', 'status',
        'price', 'bedrooms', 'bathrooms', 'city', 'postcode',
        'published_at', 'created_at', 'updated_at', 'deleted_at',
    ];

    public function __construct(private readonly PropertyService $properties) {}

    public function index(PropertyIndexRequest $request): Response
    {
        $filters = $request->filters();

        $properties = Property::query()
            // Agents see only their agency's properties (PropertyIndexRequest
            // already checked PropertyPolicy::viewAny for the page itself).
            ->visibleTo($this->currentUser())
            ->select(self::LIST_COLUMNS)
            // Eager loading: 3 extra queries for the whole page instead of
            // 3 per row (the N+1 problem). "agency:id,name" also limits the columns.
            ->with(['agency:id,name', 'propertyType:id,name', 'mainImage'])
            // Counts as sub-selects in the same query; no enquiry rows are loaded.
            ->withCount(['enquiries', 'favouritedBy'])
            ->filter($filters)
            ->sortBy($filters['sort'])
            ->paginate(15)
            ->withQueryString(); // keep the filters in the page links

        return Inertia::render('admin/properties/Index', [
            'properties' => PropertyResource::collection($properties),
            'filters' => $filters,
            // A closure is only run if the page asks for this prop. The filter bar
            // reloads with only: ['properties', 'filters'], so these 6 queries
            // run on the first visit only, not on every keystroke.
            'options' => fn (): array => $this->filterOptions(),
            'sorts' => array_keys(Property::SORTS),
        ]);
    }

    public function create(): Response
    {
        // No model yet, so pass the class: PropertyPolicy::create().
        Gate::authorize('create', Property::class);

        return Inertia::render('admin/properties/Create', [
            'options' => $this->formOptions(),
        ]);
    }

    public function store(StorePropertyRequest $request): RedirectResponse
    {
        $property = $this->properties->create(
            $request->safe()->except(['images']),
            $this->uploadedImages($request),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => "Property \"{$property->title}\" created."]);

        return to_route('admin.properties.show', $property);
    }

    /**
     * The route uses withTrashed(), so a property in the trash can still be viewed.
     */
    public function show(Property $property): Response
    {
        // Gate::authorize(): calls PropertyPolicy::view(); false -> 403 Forbidden.
        Gate::authorize('view', $property);

        // load() eager loads on a model you already have. One query per relation.
        $property->load(['agency:id,name', 'propertyType:id,name', 'features:id,name', 'images'])
            ->loadCount(['enquiries', 'favouritedBy']);

        return Inertia::render('admin/properties/Show', [
            'property' => PropertyResource::make($property)->resolve(),

            'recentEnquiries' => $property->enquiries()
                ->with('user:id,name,email')
                ->latest()
                ->limit(5)
                ->get()
                // Collection::map(): shape each model into a plain array for Vue.
                ->map(fn (Enquiry $enquiry): array => [
                    'id' => $enquiry->id,
                    'message' => Str::limit($enquiry->message, 120),
                    'user' => $enquiry->user->name,
                    'email' => $enquiry->user->email,
                    'created_ago' => $enquiry->created_at?->diffForHumans(),
                ]),

            // Private notes (polymorphic, HasNotes trait). Same panel as the agency page.
            'notes' => NoteResource::collection($property->notes()->with('author:id,name')->get())->resolve(),

            // Deferred: the page renders first, then Inertia fetches this in a
            // second request. Good for slower data the user can wait a moment for.
            'enquiriesPerMonth' => Inertia::defer(fn (): array => $this->enquiriesPerMonth($property)),
        ]);
    }

    public function edit(Property $property): Response
    {
        Gate::authorize('update', $property);

        // features:id,name: PropertyResource reads the name. With shouldBeStrict(),
        // reading a column that was not selected throws instead of returning null.
        $property->load(['features:id,name', 'images']);

        return Inertia::render('admin/properties/Edit', [
            'property' => PropertyResource::make($property)->resolve(),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(UpdatePropertyRequest $request, Property $property): RedirectResponse
    {
        /** @var list<int> $removeImageIds */
        $removeImageIds = array_map('intval', $request->array('remove_images'));

        $this->properties->update(
            $property,
            $request->safe()->except(['images', 'remove_images']),
            $this->uploadedImages($request),
            $removeImageIds,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property updated.']);

        return to_route('admin.properties.show', $property);
    }

    /**
     * Soft delete (moves it to the trash).
     */
    public function destroy(Property $property): RedirectResponse
    {
        Gate::authorize('delete', $property);

        $this->properties->delete($property);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$property->title}\" moved to the trash."]);

        return to_route('admin.properties.index');
    }

    public function restore(Property $property): RedirectResponse
    {
        Gate::authorize('restore', $property);

        $this->properties->restore($property);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$property->title}\" restored."]);

        return back();
    }

    /**
     * Permanent delete, only allowed from the trash.
     */
    public function forceDelete(Property $property): RedirectResponse
    {
        // Agents never pass this (forceDelete() returns false); only admins via before().
        Gate::authorize('forceDelete', $property);

        // 409 Conflict: the request is valid, but not in the property's current state.
        abort_unless($property->trashed(), 409, 'Move the property to the trash first.');

        try {
            $this->properties->forceDelete($property);
        } catch (QueryException) {
            // enquiries.property_id uses restrictOnDelete: the database refuses.
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'This property has enquiries, so it cannot be deleted permanently.',
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property deleted permanently.']);

        return to_route('admin.properties.index', ['trashed' => 'only']);
    }

    /**
     * Publish / unpublish / trash several properties at once.
     * Validated inline with $request->validate(), the alternative to a Form Request
     * for small, one-off rules.
     */
    public function bulk(Request $request): RedirectResponse
    {
        /** @var array{action: 'publish'|'unpublish'|'delete', ids: list<int>} $data */
        $data = $request->validate([
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
        ]);

        // Keep only ids this user may see. Without this, an agent could post
        // another agency's ids and publish or trash their listings: the policy
        // only checks one model, so a bulk action must filter the list itself.
        /** @var list<int> $ids */
        $ids = Property::query()->visibleTo($this->currentUser())->whereKey($data['ids'])->pluck('id')->all();

        $count = $this->properties->bulk($data['action'], $ids);

        $verb = ['publish' => 'published', 'unpublish' => 'unpublished', 'delete' => 'moved to the trash'][$data['action']];

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "{$count} ".Str::plural('property', $count)." {$verb}.",
        ]);

        return back();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Options for the filter bar.
     *
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            ...$this->formOptions(),
            'publications' => PublicationStatus::options(),
            // distinct()->pluck(): one column of unique values, as a Collection of strings.
            'cities' => Property::query()->visibleTo($this->currentUser())->distinct()->orderBy('city')->pluck('city'),
        ];
    }

    /**
     * Options shared by the create/edit form and the filter bar.
     *
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            // Read from the cache (CachedLookups), not the database, on every page.
            // The cached list is the same for everyone; an agent's view is then
            // narrowed in PHP to their own agency (StorePropertyRequest enforces it too).
            'agencies' => $this->currentUser()->isAgent()
                ? array_values(array_filter(
                    CachedLookups::agencies(),
                    fn (array $option): bool => $option['value'] === $this->currentUser()->agency_id,
                ))
                : CachedLookups::agencies(),
            'types' => CachedLookups::propertyTypes(),
            'features' => CachedLookups::features(),
            'listingTypes' => ListingType::options(),
            'statuses' => PropertyStatus::options(),

            // mapWithKeys(): build an associative array from a list.
            // Result: ['sale' => ['available', 'under_offer', 'sold'], 'rent' => ['available', 'let']]
            // The form uses it to only offer statuses that are valid for the chosen listing type.
            'statusesByListingType' => collect(ListingType::cases())->mapWithKeys(fn (ListingType $type): array => [
                $type->value => array_map(
                    fn (PropertyStatus $status): string => $status->value,
                    PropertyStatus::forListingType($type),
                ),
            ]),
        ];
    }

    /**
     * Enquiries per month for the last 6 months, including months with zero.
     * A small tour of Collection methods.
     *
     * @return list<array{month: string, count: int}>
     */
    private function enquiriesPerMonth(Property $property): array
    {
        $from = now()->startOfMonth()->subMonths(5);

        // groupBy() + count(): ['2026-09' => 3, '2026-10' => 1]
        $counts = $property->enquiries()
            ->where('created_at', '>=', $from)
            ->pluck('created_at')
            ->groupBy(fn ($date): string => $date->format('Y-m'))
            ->map(fn (Collection $group): int => $group->count());

        // times(): build the 6 months ourselves, so a month with no enquiries
        // still appears with 0 instead of being missing from the chart.
        $months = Collection::times(6, fn (int $i) => $from->addMonths($i - 1))
            ->map(fn ($month): array => [
                'month' => $month->format('M Y'),
                'count' => $counts->get($month->format('Y-m'), 0),
            ]);

        return array_values($months->all());
    }

    /**
     * The logged-in admin or agent (the route group guarantees one exists).
     */
    private function currentUser(): User
    {
        /** @var User */
        return request()->user();
    }

    /**
     * @return list<UploadedFile>
     */
    private function uploadedImages(Request $request): array
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images', []);

        return $files;
    }
}
