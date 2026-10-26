<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PropertyIndexRequest;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Models\Property;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public property search for API clients. No token needed.
 */
class PropertyController extends Controller
{
    /**
     * cursorPaginate(), not paginate(): a mobile app scrolls endlessly.
     *   paginate():       LIMIT 15 OFFSET 7485 -> reads and throws away 7,485 rows,
     *                     and rows shift when new listings arrive mid-scroll.
     *   cursorPaginate(): WHERE (created_at, id) < (last row seen) LIMIT 15
     *                     -> jumps straight there via the index, stable while scrolling.
     * The trade-off: no page numbers or total, only next/prev cursors.
     * It needs a unique ORDER BY, which sortBy()'s id tie-breaker provides.
     */
    public function index(PropertyIndexRequest $request): AnonymousResourceCollection
    {
        $properties = Property::query()
            ->published()
            ->filter($request->filters())
            ->sortBy($request->sort())
            // List columns only: no description, so each page stays small.
            ->select([
                'id', 'title', 'slug', 'listing_type', 'status', 'price', 'bedrooms',
                'bathrooms', 'city', 'postcode', 'published_at', 'property_type_id', 'created_at', 'latitude', 'longitude',
            ])
            // Eager load: 3 queries per page instead of 1 + 15 + 15 (N+1).
            ->with(['propertyType:id,name', 'mainImage'])
            ->cursorPaginate($request->perPage())
            ->withQueryString();

        return PropertyResource::collection($properties);
    }

    public function show(Property $property): PropertyResource
    {
        // Drafts and scheduled listings must look like they do not exist.
        // 404, not 403: a 403 would confirm that the slug is real.
        abort_unless($property->publication_status === PublicationStatus::Published, 404);

        $property->load(['agency:id,name', 'propertyType:id,name', 'features:id,name', 'images']);

        return new PropertyResource($property);
    }
}
