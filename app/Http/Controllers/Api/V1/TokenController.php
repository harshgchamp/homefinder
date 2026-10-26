<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TokenResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * "Your devices": every token is one logged-in device (one login = one token).
 */
class TokenController extends Controller
{
    /**
     * A user has a handful of devices, so no pagination is needed.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tokens = $this->user($request)->tokens()
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('last_used_at')
            ->get();

        return TokenResource::collection($tokens);
    }

    /**
     * Log out one device, e.g. a lost phone.
     *
     * The lookup goes through MY tokens(), so it is also the authorization:
     * another user's token id is simply "not found" (404). A 403 would confirm
     * that the id exists.
     */
    public function destroy(Request $request, int $tokenId): Response
    {
        $this->user($request)->tokens()->whereKey($tokenId)->firstOrFail()->delete();

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
