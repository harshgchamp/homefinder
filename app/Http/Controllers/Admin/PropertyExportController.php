<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PropertyIndexRequest;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Download the filtered property list as CSV.
 *
 * Built for large tables. Two things keep memory flat whether there are
 * 100 or 1,000,000 rows:
 *
 *  1. lazyById(500): loads 500 models, hands them out one by one, then loads the
 *     next 500 (WHERE id > last id). Only one chunk is ever in memory.
 *     ->get() would load every row at once and could run out of memory.
 *
 *  2. streamDownload(): each CSV line is sent to the browser as soon as it is
 *     written, instead of building the whole file in memory first.
 */
class PropertyExportController extends Controller
{
    public function __invoke(PropertyIndexRequest $request): StreamedResponse|RedirectResponse
    {
        // The same filter() scope as the list page, so the CSV matches what the admin sees.
        /** @var User $user */
        $user = $request->user();

        $query = Property::query()
            ->visibleTo($user) // agents export only their agency's listings
            ->with(['agency:id,name', 'propertyType:id,name']) // eager loaded per chunk of 500
            ->filter($request->filters());

        // Atomic lock: only one export per user at a time. Several big exports in
        // parallel (double clicks, many tabs) would each walk the whole table.
        // get() tries ONCE and returns false straight away if someone holds it.
        // "Atomic": Redis creates the lock in one step, so two requests arriving at
        // the same moment can never both get it (unlike "check, then set").
        // The 300s expiry frees the lock even if this process dies mid-export.
        $lock = Cache::lock("exports:user:{$user->id}", 300);

        if (! $lock->get()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'Your previous export is still running. Please wait for it to finish.']);

            return back();
        }

        $filename = 'properties-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query, $lock): void {
            // The body is streamed AFTER this method returns, so the lock is
            // released here, at the end of streaming, not when the method ends.
            try {
                $this->writeCsv($query);
            } finally {
                $lock->release();
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  Builder<Property>  $query
     */
    private function writeCsv(Builder $query): void
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            return;
        }

        fputcsv($out, ['ID', 'Title', 'Agency', 'Type', 'Listing', 'Status', 'Price', 'Beds', 'Baths', 'City', 'Postcode', 'Publication', 'Created']);

        // Note: lazyById() always walks in id order, so the list's ?sort= is not
        // applied here. Sorting a million rows would defeat the purpose.
        foreach ($query->lazyById(500) as $property) {
            fputcsv($out, array_map($this->safeCell(...), [
                $property->id,
                $property->title,
                $property->agency->name,
                $property->propertyType->name,
                $property->listing_type->label(),
                $property->status->label(),
                $property->price,
                $property->bedrooms,
                $property->bathrooms,
                $property->city,
                $property->postcode,
                $property->publication_status->label(),
                $property->created_at?->toDateString(),
            ]));
        }

        fclose($out);
    }

    /**
     * CSV injection: Excel runs a cell that starts with = + - @ as a formula.
     * A title like "=HYPERLINK(...)" could attack whoever opens the file.
     * A leading apostrophe makes Excel show it as plain text.
     */
    private function safeCell(string|int|null $value): string|int|null
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
