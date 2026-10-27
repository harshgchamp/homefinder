<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePropertyTypeRequest;
use App\Http\Requests\Admin\UpdatePropertyTypeRequest;
use App\Http\Resources\PropertyTypeResource;
use App\Models\PropertyType;
use App\Services\PropertyTypeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin CRUD for property types (House, Flat, Bungalow...). Thin, like the
 * other admin controllers: validation in Form Requests, logic in the service.
 *
 * Create and Edit render two small pages that share ONE form component,
 * PropertyTypeForm.vue. Edit passes the type, Create passes nothing.
 */
class PropertyTypeController extends Controller
{
    public function __construct(private readonly PropertyTypeService $types) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->squish()->value();

        $types = PropertyType::query()
            ->when($search, fn ($query, string $term) => $query->whereLike('name', "%{$term}%"))
            ->withCount('properties')   // one COUNT subquery, not one query per row
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();        // keep ?search= in the pagination links

        return Inertia::render('admin/property-types/Index', [
            'types' => PropertyTypeResource::collection($types),
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        // No props: the shared form sees no "type" and knows it is creating.
        return Inertia::render('admin/property-types/Create');
    }

    public function store(StorePropertyTypeRequest $request): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validated();

        $type = $this->types->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Property type \"{$type->name}\" created."]);

        return to_route('admin.property-types.index');
    }

    public function edit(PropertyType $propertyType): Response
    {
        return Inertia::render('admin/property-types/Edit', [
            // The existing type: the shared form sees it and switches to edit mode.
            // resolve() gives a plain object, without the { data: ... } wrapper.
            'type' => PropertyTypeResource::make($propertyType->loadCount('properties'))->resolve(),
        ]);
    }

    public function update(UpdatePropertyTypeRequest $request, PropertyType $propertyType): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validated();

        $this->types->update($propertyType, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type updated.']);

        return to_route('admin.property-types.index');
    }

    public function destroy(PropertyType $propertyType): RedirectResponse
    {
        // Checked here, not left to the database: a foreign key error would be a
        // 500 page, this is a clear message.
        if (! $this->types->canDelete($propertyType)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "\"{$propertyType->name}\" is still used by properties (including trashed ones). Change their type first.",
            ]);

            return back();
        }

        $this->types->delete($propertyType);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Property type deleted.']);

        return to_route('admin.property-types.index');
    }
}
