<?php

namespace App\Events;

use App\Models\Enquiry;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A user sent an enquiry about a property.
 *
 * An event is a plain object that describes something that ALREADY happened
 * (past tense: "EnquiryReceived", not "ReceiveEnquiry"). It holds the data
 * listeners need and contains no logic itself.
 *
 * Fired by:      EnquiryService::create()   EnquiryReceived::dispatch($enquiry)
 * Listened to:   app/Listeners/*  (found automatically: any listener whose
 *                handle() method type-hints this class). See `php artisan event:list`.
 *
 * ShouldDispatchAfterCommit: when dispatched inside DB::transaction(), the event
 * waits until the transaction commits. If it rolls back, the enquiry was never
 * saved, so the event is never sent and no listener reacts to something that
 * did not happen.
 */
class EnquiryReceived implements ShouldDispatchAfterCommit
{
    // Dispatchable: adds the static EnquiryReceived::dispatch(...) helper.
    // SerializesModels: if a listener is queued, only the model's id is stored
    // in the queue, and a fresh copy is loaded from the database when the job runs.
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Enquiry $enquiry,
    ) {}
}
