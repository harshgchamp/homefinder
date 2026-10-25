<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AgencyIndexRequest;
use App\Http\Requests\Admin\StoreAgencyRequest;
use App\Http\Requests\Admin\UpdateAgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use App\Services\AgencyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function __construct(private readonly AgencyService $agencies) {}

    public function index(AgencyIndexRequest $request): Response
    {
        $filters = $request->filters();

        $agencies = Agency::query()
            ->search($filters['search'])
            ->status($filters['status'])
            // One COUNT sub-select, not one query per row. Also needed by the
            // "most_properties" sort, which orders by this alias.
            ->withCount('properties')
            ->sortBy($filters['sort'])
            ->paginate(10)
            ->withQueryString(); // keep ?search=&status=&sort= in the page links

        return Inertia::render('admin/agencies/Index', [
            'agencies' => AgencyResource::collection($agencies),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/agencies/Create');
    }

    public function store(StoreAgencyRequest $request): RedirectResponse
    {
        /** @var array{name: string, email: string, phone: string, is_active: bool} $data */
        $data = $request->validated();

        $agency = $this->agencies->create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Agency \"{$agency->name}\" created."]);

        return to_route('admin.agencies.index');
    }

    public function edit(Agency $agency): Response
    {
        return Inertia::render('admin/agencies/Edit', [
            // resolve() gives a plain array, without the { data: ... } wrapper
            'agency' => AgencyResource::make($agency->loadCount('properties'))->resolve(),
        ]);
    }

    public function update(UpdateAgencyRequest $request, Agency $agency): RedirectResponse
    {
        /** @var array{name: string, email: string, phone: string, is_active: bool} $data */
        $data = $request->validated();

        $this->agencies->update($agency, $data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agency updated.']);

        return to_route('admin.agencies.index');
    }

    public function destroy(Agency $agency): RedirectResponse
    {
        // Checked here, not left to the database: a foreign key error would be a
        // 500 page, this is a clear message. Deactivating is the alternative.
        if (! $this->agencies->canDelete($agency)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => "\"{$agency->name}\" still has properties (including trashed ones). Move or delete them first, or mark the agency inactive.",
            ]);

            return back();
        }

        $this->agencies->delete($agency);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Agency \"{$agency->name}\" deleted."]);

        return to_route('admin.agencies.index');
    }
}
