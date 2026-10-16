<?php

namespace App\Http\Resources;

use App\Models\Feature;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Feature
 */
class FeatureResource extends JsonResource
{
    /**
     * Only these fields reach the browser, whatever columns the table gains later.
     *
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
            // Accessor on the model.
            'is_new' => $this->is_new,
            // Dates, formatted on the server so every page shows them the same way.
            'created_at' => $this->created_at?->format('j M Y'),       // 10 Oct 2026
            'created_ago' => $this->created_at?->diffForHumans(),      // 3 days ago
            'updated_at' => $this->updated_at?->format('j M Y, H:i'),  // 10 Oct 2026, 14:07
        ];
    }
}
