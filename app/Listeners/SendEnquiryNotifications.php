<?php

namespace App\Listeners;

use App\Events\EnquiryReceived;
use App\Mail\EnquirySent;
use App\Models\User;
use App\Notifications\NewEnquiryNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/**
 * The third listener on EnquiryReceived. Adding it changed nothing in the event,
 * the controller or the service: that is the point of events.
 *
 * This listener itself runs synchronously and is fast: it only PUTS emails and
 * notifications on the queue (each of them implements ShouldQueue). The slow part,
 * talking to the mail server, happens later in the worker, one job per message.
 */
class SendEnquiryNotifications
{
    public function handle(EnquiryReceived $event): void
    {
        // Load what the emails need once, here. The queued jobs restore these
        // relations too (SerializesModels remembers which ones were loaded).
        $enquiry = $event->enquiry->loadMissing(['property.agency', 'user']);

        // notify(): one recipient. The agency gets an email (see via()).
        $enquiry->property->agency->notify(new NewEnquiryNotification($enquiry));

        // Notification::send(): many recipients at once. Admins get a database row.
        Notification::send(
            User::query()->where('is_admin', true)->get(),
            new NewEnquiryNotification($enquiry),
        );

        // A Mailable to the person who enquired. Queued, because EnquirySent
        // implements ShouldQueue.
        Mail::to($enquiry->user)->send(new EnquirySent($enquiry));
    }
}
