<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Property;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => $this->stats(),
        ]);
    }

    /**
     * @return array{published: int, drafts: int, scheduled: int, agencies: int, enquiries_last_7_days: int}
     */
    private function stats(): array
    {
        $now = now();

        // One query for all three property counts (conditional aggregation).
        $properties = Property::query()
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
            'enquiries_last_7_days' => Enquiry::where('created_at', '>=', $now->copy()->subDays(7))->count(),
        ];
    }
}
