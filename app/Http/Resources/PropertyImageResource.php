<?php

namespace App\Http\Resources;

use App\Models\PropertyImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PropertyImage
 */
class PropertyImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // The browser needs a URL; the stored path stays on the server.
            'url' => $this->url,
            'sort_order' => $this->sort_order,
        ];
    }
}
