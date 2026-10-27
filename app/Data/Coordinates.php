<?php

namespace App\Data;

use App\Casts\CoordinatesCast;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use InvalidArgumentException;
use JsonSerializable;

/**
 * A point on the map: a small, immutable VALUE OBJECT.
 *
 * Value object = defined by its values, not by an id. Two Coordinates with
 * the same latitude and longitude are the same point. It cannot change after
 * it is built (readonly), and it checks its own rules in the constructor, so
 * an invalid point (latitude 500) can never exist anywhere in the app.
 *
 * Castable: the model writes 'location' => Coordinates::class, and this class
 * names the cast that converts it (castUsing below). The model never needs to
 * know about CoordinatesCast.
 */
final readonly class Coordinates implements Castable, JsonSerializable
{
    /**
     * Mean radius of the Earth, used by distanceTo().
     */
    private const EARTH_RADIUS_KM = 6371;

    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
        if ($latitude < -90 || $latitude > 90) {
            throw new InvalidArgumentException("Latitude must be between -90 and 90, {$latitude} given.");
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new InvalidArgumentException("Longitude must be between -180 and 180, {$longitude} given.");
        }
    }

    /**
     * Straight-line ("as the crow flies") distance in km, with the haversine
     * formula: the Earth is round, so plain Pythagoras on degrees would be wrong.
     */
    public function distanceTo(self $other): float
    {
        $latFrom = deg2rad($this->latitude);
        $latTo = deg2rad($other->latitude);
        $latDelta = $latTo - $latFrom;
        $lngDelta = deg2rad($other->longitude - $this->longitude);

        $a = sin($latDelta / 2) ** 2 + cos($latFrom) * cos($latTo) * sin($lngDelta / 2) ** 2;

        return self::EARTH_RADIUS_KM * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Same values = same point (value objects compare by value, not identity).
     */
    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude && $this->longitude === $other->longitude;
    }

    /**
     * @return array{latitude: float, longitude: float}
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }

    /**
     * JsonSerializable: json_encode($coordinates) and $property->toArray()
     * give {"latitude": .., "longitude": ..} instead of an empty object.
     *
     * @return array{latitude: float, longitude: float}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Castable: called by Eloquent to get the cast for 'location' => Coordinates::class.
     *
     * @param  array<int, string>  $arguments  (from 'location' => Coordinates::class.':arg', unused)
     * @return CastsAttributes<Coordinates|null, mixed>
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        return new CoordinatesCast;
    }
}
