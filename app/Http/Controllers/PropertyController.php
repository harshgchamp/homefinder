<?php

namespace App\Http\Controllers;

use App\Enums\PublicationStatus;
use App\Http\Requests\StoreEnquiryRequest;
use App\Http\Resources\PropertyResource;
use App\Models\Enquiry;
use App\Models\Property;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public side of properties (anyone can view). The admin side is
 * Admin\PropertyController.
 */
class PropertyController extends Controller
{
    /**
     * /properties/{property:slug}: bound by slug, not id, so URLs are readable
     * (/properties/3-bed-house-romford) and ids are not exposed.
     */
    public function show(Request $request, Property $property): Response
    {
        // Drafts and scheduled listings must look like they do not exist.
        // 404, not 403: a 403 would confirm that the URL is real.
        abort_unless($property->publication_status === PublicationStatus::Published, 404);

        $property->load(['agency:id,name', 'propertyType:id,name', 'features:id,name', 'images']);

        $user = $request->user();

        return Inertia::render('public/PropertyShow', [
            'property' => PropertyResource::make($property)->resolve(),
            // Lets the page show "already sent" instead of a form that would be rejected.
            'enquiredRecently' => $user !== null && Enquiry::query()
                ->where('user_id', $user->id)
                ->where('property_id', $property->id)
                ->where('created_at', '>=', now()->subHours(StoreEnquiryRequest::REPEAT_AFTER_HOURS))
                ->exists(),
        ]);
    }
}
