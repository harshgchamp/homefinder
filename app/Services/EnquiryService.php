<?php

namespace App\Services;

use App\Events\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnquiryService
{
    /**
     * Save an enquiry and announce it.
     *
     * Why the event is dispatched HERE (in the service), not in the controller:
     * an enquiry might later also arrive through an API or an import. Every path
     * goes through this method, so the event is always fired, exactly once.
     *
     * Why not a model event ($dispatchesEvents = ['created' => ...]) instead:
     * that would also fire for seeders, factories and tests that create enquiries
     * as data. "Received" is a business moment, so it is announced explicitly.
     *
     * @param  array{message: string, phone?: string|null}  $data  Validated input.
     */
    public function create(User $user, Property $property, array $data): Enquiry
    {
        return DB::transaction(function () use ($user, $property, $data): Enquiry {
            // make(): a new Enquiry with property_id already set by the relation.
            $enquiry = $property->enquiries()->make($data);

            // user_id is deliberately NOT in Enquiry::$fillable, so a request can
            // never claim to be another user. It is set here from the logged-in user.
            $enquiry->user()->associate($user);
            $enquiry->save();

            // Dispatched inside the transaction, but EnquiryReceived implements
            // ShouldDispatchAfterCommit, so listeners only run once the row is committed.
            EnquiryReceived::dispatch($enquiry);

            return $enquiry;
        });
    }
}
