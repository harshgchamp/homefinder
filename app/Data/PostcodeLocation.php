<?php

namespace App\Data;

/**
 * The answer from a PostcodeLookup: a small, immutable value object.
 *
 * Why not return the provider's raw array: every provider's JSON looks different.
 * Each implementation translates its own response into this ONE shape, so the
 * rest of the app never knows which provider answered.
 *
 * readonly class: all properties can only be set once, in the constructor.
 */
final readonly class PostcodeLocation
{
    public function __construct(
        public string $postcode,
        public float $latitude,
        public float $longitude,
        public ?string $district = null,
    ) {}

    /**
     * @return array{postcode: string, latitude: float, longitude: float, district: string|null}
     */
    public function toArray(): array
    {
        return [
            'postcode' => $this->postcode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'district' => $this->district,
        ];
    }

    /**
     * @param  array{postcode: string, latitude: float, longitude: float, district?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['postcode'], $data['latitude'], $data['longitude'], $data['district'] ?? null);
    }
}
