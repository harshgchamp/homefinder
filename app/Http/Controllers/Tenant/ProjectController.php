<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;

class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Project::query()->latest('id')->paginate(20));
    }

    /**
     * int, not Project: route model binding would query before
     * IdentifyTenant has opened the tenant database.
     */
    public function show(string $organisation, int $project): JsonResponse
    {
        return response()->json(Project::query()->findOrFail($project));
    }
}
