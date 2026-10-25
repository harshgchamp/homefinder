<?php

namespace App\Http\Resources;

use App\Models\Agency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Agency
 */
class AgencyResource extends JsonResource
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
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            // Present only when the query used withCount('properties').
            'properties_count' => $this->whenCounted('properties'),
            'created_at' => $this->created_at?->format('j M Y'),
            'created_ago' => $this->created_at?->diffForHumans(),
        ];
    }
}
