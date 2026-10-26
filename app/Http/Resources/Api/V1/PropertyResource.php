<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\PropertyImageResource;
use App\Models\Feature;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public API shape of a property: a CONTRACT with mobile apps, so fields
 * are only added here, never renamed or removed (that would be a v2).
 *
 * Kept separate from App\Http\Resources\PropertyResource (the admin pages):
 * no internal fields (agency_id, trashed, permissions), and dates in
 * ISO 8601, which every client language can parse.
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            // Only the detail endpoint selects it, so list pages stay small.
            'description' => $this->whenHas('description'),

            'listing_type' => ['value' => $this->listing_type->value, 'label' => $this->listing_type->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],

            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'furnished' => $this->whenHas('furnished'),

            'city' => $this->city,
            'postcode' => $this->postcode,
            'location' => $this->whenHas('latitude', fn () => $this->latitude !== null && $this->longitude !== null
                ? ['latitude' => $this->latitude, 'longitude' => $this->longitude]
                : null),

            'published_at' => $this->published_at?->toIso8601String(),

            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency->id,
                'name' => $this->agency->name,
            ]),
            'property_type' => $this->whenLoaded('propertyType', fn () => [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
            ]),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn (Feature $feature): array => [
                'id' => $feature->id,
                'name' => $feature->name,
            ])),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
            'main_image_url' => $this->whenLoaded('mainImage', fn () => $this->mainImage?->url),
        ];
    }
}
