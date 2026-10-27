<?php

namespace App\Http\Resources;

use App\Models\PropertyType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertyType
 */
class PropertyTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            // Present only when the query used withCount('properties').
            'properties_count' => $this->whenCounted('properties'),
            'created_at' => $this->created_at?->format('j M Y'),
            'created_ago' => $this->created_at?->diffForHumans(),
            'updated_at' => $this->updated_at?->format('j M Y, H:i'),
        ];
    }
}
