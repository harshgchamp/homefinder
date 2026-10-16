<?php

namespace App\Mail;

use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Confirmation email to the person who sent the enquiry.
 *
 * A Mailable (not a Notification) because it is only ever an email, to one
 * person, with its own designed template. Notifications are for "tell someone
 * about an event, through one or more channels".
 *
 * ShouldQueue: Mail::to(...)->send(new EnquirySent(...)) is queued automatically.
 */
class EnquirySent extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Public properties are available in the Blade template as variables ($enquiry).
     */
    public function __construct(public Enquiry $enquiry) {}

    /**
     * The "outside" of the email: subject, from, reply-to, tags.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your enquiry about {$this->enquiry->property->title}",
        );
    }

    /**
     * The "inside": a markdown Blade template, rendered into HTML and plain text.
     */
    public function content(): Content
    {
        return new Content(markdown: 'mail.enquiry-sent');
    }
}
