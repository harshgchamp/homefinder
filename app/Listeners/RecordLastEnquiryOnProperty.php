<?php

namespace App\Listeners;

use App\Events\EnquiryReceived;
use App\Models\Property;

/**
 * Stores when a property last received an enquiry (properties.last_enquiry_at).
 * Used by the archive command so a listing people are still asking about is
 * never unpublished as "stale".
 *
 * How Laravel finds this listener: it scans app/Listeners and reads the type
 * hint of handle(). Because the parameter is EnquiryReceived, this runs every
 * time that event is dispatched. No registration anywhere.
 *
 * Runs synchronously (no ShouldQueue): one quick UPDATE on our own database.
 */
class RecordLastEnquiryOnProperty
{
    public function handle(EnquiryReceived $event): void
    {
        $receivedAt = $event->enquiry->created_at ?? now();

        Property::query()
            ->whereKey($event->enquiry->property_id)
            // Only move the date FORWARD. If this listener is ever queued and
            // two enquiries are processed out of order, an older one cannot
            // overwrite a newer date. Safe to run twice (idempotent), too.
            ->where(fn ($query) => $query
                ->whereNull('last_enquiry_at')
                ->orWhere('last_enquiry_at', '<', $receivedAt))
            // toBase(): a plain query builder UPDATE. The Eloquent version would
            // also set updated_at, and an enquiry does not "edit" the property.
            ->toBase()
            ->update(['last_enquiry_at' => $receivedAt]);
    }
}
