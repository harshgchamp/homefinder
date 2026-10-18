<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => $this->stats($request->user()),
        ]);
    }

    /**
     * @return array{published: int, drafts: int, scheduled: int, agencies: int, enquiries_last_7_days: int}
     */
    private function stats(User $user): array
    {
        $now = now();

        // One query for all three property counts (conditional aggregation).
        // visibleTo(): an agent's dashboard counts only their agency's listings.
        $properties = Property::query()
            ->visibleTo($user)
            ->toBase()
            ->selectRaw(
                'COUNT(CASE WHEN published_at <= ? THEN 1 END) AS published,
                 COUNT(CASE WHEN published_at IS NULL THEN 1 END) AS drafts,
                 COUNT(CASE WHEN published_at > ? THEN 1 END) AS scheduled',
                [$now, $now],
            )
            ->first();

        return [
            'published' => (int) ($properties->published ?? 0),
            'drafts' => (int) ($properties->drafts ?? 0),
            'scheduled' => (int) ($properties->scheduled ?? 0),
            'agencies' => Agency::count(),
            'enquiries_last_7_days' => Enquiry::query()
                ->where('created_at', '>=', $now->copy()->subDays(7))
                // whereHas + the same scope: enquiries on properties this user may see.
                ->whereHas('property', fn (Builder $q) => $q->visibleTo($user))
                ->count(),
        ];
    }
}
