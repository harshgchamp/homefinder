<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreFeatureRequest;
use App\Http\Requests\Admin\UpdateFeatureRequest;
use App\Http\Resources\FeatureResource;
use App\Models\Feature;
use App\Services\FeatureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class FeatureController extends Controller
{
    public function __construct(private readonly FeatureService $features) {}

    public function index(Request $request): Response
    {
        $search = $request->string('search')->squish()->value();

        $features = Feature::query()
            ->search($search)
            ->withCount('properties')   // one extra COUNT, not one query per row
            ->orderBy('name')
            ->paginate(5)
            ->withQueryString();        // keep ?search= in the pagination links

        return Inertia::render('admin/features/Index', [
            // Paginated resource collection: { data: [...], links: {...}, meta: {...} }
            'features' => FeatureResource::collection($features),
            // Plain array
            'filters' => ['search' => $search],
            // Plain numbers
            'total' => Feature::count(),
            'addedThisWeek' => Feature::query()->where('created_at', '>=', now()->startOfWeek())->count(),
            // Collection of strings, arrives in Vue as a JSON array
            'recentNames' => Feature::query()->latest()->limit(5)->pluck('name'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/features/Create');
    }

    public function store(StoreFeatureRequest $request): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validated();

        $feature = $this->features->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Feature \"{$feature->name}\" created."]);

        return to_route('admin.features.index');
    }

    public function edit(Feature $feature): Response
    {
        return Inertia::render('admin/features/Edit', [
            // resolve() gives a plain array, without the { data: ... } wrapper
            'feature' => FeatureResource::make($feature)->resolve(),
        ]);
    }

    public function update(UpdateFeatureRequest $request, Feature $feature): RedirectResponse
    {
        /** @var array{name: string} $data */
        $data = $request->validated();

        $this->features->update($feature, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Feature updated.']);

        return to_route('admin.features.index');
    }

    public function destroy(Feature $feature): RedirectResponse
    {
        // Count before deleting so the message can say what was affected.
        $used = $feature->properties()->count();

        $this->features->delete($feature);

        $message = $used > 0
            ? "Feature deleted and removed from {$used} ".Str::plural('property', $used).'.'
            : 'Feature deleted.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('admin.features.index');
    }
}
