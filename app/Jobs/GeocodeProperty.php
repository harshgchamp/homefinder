<?php

namespace App\Jobs;

use App\Contracts\PostcodeLookup;
use App\Exceptions\PostcodeNotFound;
use App\Models\Property;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Fill in a property's latitude/longitude from its postcode.
 *
 * Queued, because it calls a third-party API: if postcodes.io is slow or
 * down, the admin's "Save" must not wait or fail because of it.
 */
class GeocodeProperty implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * An outage can last a while: 5 tries over about 20 minutes.
     */
    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120, 300, 900];

    public bool $deleteWhenMissingModels = true;

    public function __construct(public Property $property) {}

    public function uniqueId(): string
    {
        return (string) $this->property->id;
    }

    /**
     * Method injection: the container passes the PostcodeLookup bound in
     * AppServiceProvider (cached real API, or the fake in tests). This job
     * does not know or care which.
     */
    public function handle(PostcodeLookup $postcodes): void
    {
        try {
            $location = $postcodes->lookup($this->property->postcode);
        } catch (PostcodeNotFound) {
            // The user's data is wrong; retrying cannot fix it. Clear any old
            // coordinates and stop, without counting it as a failed job.
            $this->saveLocation(null, null);

            return;
        }
        // App\Exceptions\PostcodeLookupUnavailable is deliberately NOT caught: letting it bubble
        // up makes the queue retry later with the backoff above.

        $this->saveLocation($location->latitude, $location->longitude);
    }

    private function saveLocation(?float $latitude, ?float $longitude): void
    {
        // toBase(): a plain UPDATE, so the property's updated_at is not changed
        // (finding a postcode on the map is not an edit by the admin).
        // It also skips model casts, so the two columns are written directly here
        // rather than through $property->location (the Coordinates cast).
        Property::query()
            ->whereKey($this->property->id)
            ->toBase()
            ->update(['latitude' => $latitude, 'longitude' => $longitude]);
    }

    /**
     * Labels shown in Horizon (Block 8), to find all jobs for one property.
     *
     * @return list<string>
     */
    public function tags(): array
    {
        return ['geocode', 'property:'.$this->property->id];
    }
}
