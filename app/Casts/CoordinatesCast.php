<?php

namespace App\Casts;

use App\Data\Coordinates;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Turns the latitude + longitude COLUMNS into ONE Coordinates object, and back.
 *
 * "location" has no column of its own: it is a virtual attribute built from
 * two real ones (a multi-column value object cast).
 *
 *   $property->location                       -> get(): Coordinates or null
 *   $property->location = new Coordinates(..) -> set(): fills both columns
 *
 * Generics: get() returns Coordinates|null; set() accepts mixed, because
 * anything can be assigned and set() rejects what is not Coordinates or null.
 *
 * @implements CastsAttributes<Coordinates|null, mixed>
 */
class CoordinatesCast implements CastsAttributes
{
    /**
     * By default Eloquent CACHES the object get() returned and keeps handing it
     * back. It does not notice when a column changes underneath:
     *   $property->latitude = 52.0;  $property->location->latitude  // still the old 51.5
     * Building two floats is cheap, so build a fresh object on every read instead.
     */
    public bool $withoutObjectCaching = true;

    /**
     * Reading. $value is null (there is no "location" column); the real data
     * is in $attributes, the raw column values of the row.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Coordinates
    {
        $latitude = $attributes['latitude'] ?? null;
        $longitude = $attributes['longitude'] ?? null;

        // Not geocoded yet (the GeocodeProperty job has not run), or the postcode
        // was not found: no point on the map rather than a half-filled one.
        if ($latitude === null || $longitude === null) {
            return null;
        }

        // decimal columns arrive from MySQL as strings ("51.5754000").
        return new Coordinates((float) $latitude, (float) $longitude);
    }

    /**
     * Writing. Returning an ARRAY tells Eloquent to set several columns at once.
     * null clears both, so a property can never have only one of the two.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{latitude: float|null, longitude: float|null}
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value !== null && ! $value instanceof Coordinates) {
            throw new InvalidArgumentException('The location must be a Coordinates object or null.');
        }

        return [
            'latitude' => $value?->latitude,
            'longitude' => $value?->longitude,
        ];
    }
}
