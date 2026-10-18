<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => $this->cachedStats($request->user()),
        ]);
    }

    /**
     * The numbers are a few aggregate queries over the whole properties table.
     * Being up to a minute old is fine for a dashboard, so they are cached.
     *
     * Cache::flexible($key, [fresh, stale], ...): "stale-while-revalidate".
     *   0-60s old    -> served from cache
     *   60-300s old  -> the OLD value is served at once, and a refresh runs
     *                   after the response is sent, so nobody waits for it
     *   300s+ old    -> recalculated before responding, like Cache::remember
     *
     * The key includes WHO is asking: an agent's stats only cover their agency
     * (visibleTo), so they must never be served an admin's numbers, or another
     * agency's. One cache entry per visibility scope, not per user.
     *
     * @return array{published: int, drafts: int, scheduled: int, agencies: int, enquiries_last_7_days: int}
     */
    private function cachedStats(User $user): array
    {
        $scope = $user->is_admin ? 'all' : 'agency:'.$user->agency_id;

        return Cache::flexible("dashboard:stats:{$scope}", [60, 300], fn (): array => $this->stats($user));
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
