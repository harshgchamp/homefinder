<?php

namespace App\Listeners;

use App\Events\EnquiryReceived;
use Illuminate\Support\Facades\Log;

/**
 * Writes one line per enquiry to storage/logs/enquiries-YYYY-MM-DD.log
 * (the "enquiries" channel in config/logging.php).
 *
 * A second, independent reaction to the same event: it knows nothing about
 * RecordLastEnquiryOnProperty, and the code that dispatches the event knows
 * nothing about either of them.
 */
class LogEnquiry
{
    public function handle(EnquiryReceived $event): void
    {
        $enquiry = $event->enquiry;

        // Ids only. The message and phone number are personal data and do not
        // belong in log files, which are copied around and kept for 90 days.
        Log::channel('enquiries')->info('Enquiry received', [
            'enquiry_id' => $enquiry->id,
            'property_id' => $enquiry->property_id,
            'user_id' => $enquiry->user_id,
        ]);
    }
}
