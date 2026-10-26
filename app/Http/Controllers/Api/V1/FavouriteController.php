<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PropertyResource;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * The token owner's saved properties (the "favourites" pivot table).
 *
 * PUT and DELETE, not POST: both are IDEMPOTENT, sending them twice has the
 * same result as once. Mobile networks retry requests, and a retried POST
 * could add a duplicate; a retried PUT just means "still a favourite".
 */
class FavouriteController extends Controller
{
    /**
     * paginate() (offset) here, cursorPaginate() for the property search:
     * a user's favourites are a short list, and a total ("12 saved") is useful.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $favourites = $this->user($request)->favourites()
            // A favourite that went back to draft drops out of the list.
            ->published()
            ->with(['propertyType:id,name', 'mainImage'])
            ->orderByPivot('created_at', 'desc')
            ->paginate(15);

        return PropertyResource::collection($favourites);
    }

    public function store(Request $request, Property $property): Response
    {
        abort_unless($property->publication_status === PublicationStatus::Published, 404);

        // Adds the pivot row only if it is missing, so a repeat is harmless.
        $this->user($request)->favourites()->syncWithoutDetaching([$property->id]);

        return response()->noContent();
    }

    public function destroy(Request $request, Property $property): Response
    {
        // Removing a favourite that is already gone is still a success.
        $this->user($request)->favourites()->detach($property->id);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
