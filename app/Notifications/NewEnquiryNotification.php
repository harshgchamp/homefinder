<?php

namespace App\Notifications;

use App\Models\Agency;
use App\Models\Enquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * "Someone enquired about a property", sent to two kinds of recipient:
 *
 *   Agency (owns the listing)  -> an EMAIL they can reply to
 *   Admin users                -> a row in the notifications table, shown in admin
 *
 * One notification class, several channels: via() picks the channel per recipient.
 * That is the main difference from a Mailable, which is only ever an email.
 *
 * ShouldQueue: every recipient + channel pair becomes its own queued job, so a
 * slow or failing mail server never delays the database notifications, and each
 * one is retried on its own.
 */
class NewEnquiryNotification extends Notification implements ShouldQueue
{
    // Queueable: onQueue(), delay(), afterCommit(). The Notification base class
    // already uses SerializesModels, so only the enquiry's id goes on the queue.
    use Queueable;

    public function __construct(public Enquiry $enquiry) {}

    /**
     * Which channels to use for THIS recipient.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof Agency ? ['mail'] : ['database'];
    }

    /**
     * The email to the agency. Uses Laravel's ready-made notification email
     * layout (logo, button, footer), so no Blade template is needed.
     */
    public function toMail(Agency $agency): MailMessage
    {
        $enquiry = $this->enquiry;
        $property = $enquiry->property;
        $user = $enquiry->user;

        return (new MailMessage)
            ->subject("New enquiry: {$property->title}")
            // Reply-To: the agent presses "Reply" and writes straight to the
            // person who enquired. "From" stays our address, which keeps the
            // email deliverable (sending "from" a user's address fails SPF checks).
            ->replyTo($user->email, $user->name)
            ->greeting("Hello {$agency->name},")
            ->line("{$user->name} has sent an enquiry about {$property->title} ({$property->postcode}):")
            ->line('"'.$enquiry->message.'"')
            ->line("Email: {$user->email}")
            ->lineIf($enquiry->phone !== null, "Phone: {$enquiry->phone}")
            ->action('View the listing', route('properties.show', $property->slug))
            ->line('Reply to this email to answer them directly.');
    }

    /**
     * What is stored in notifications.data (as JSON) for the database channel.
     * Keep it small and self-contained: the admin list shows it without loading
     * the enquiry, and it still makes sense if the enquiry changes later.
     *
     * @return array{enquiry_id: int, property_id: int, property_title: string, from: string, excerpt: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'enquiry_id' => $this->enquiry->id,
            'property_id' => $this->enquiry->property_id,
            'property_title' => $this->enquiry->property->title,
            'from' => $this->enquiry->user->name,
            'excerpt' => Str::limit($this->enquiry->message, 80),
        ];
    }

    /**
     * Stored in notifications.type instead of the full class name, so renaming
     * or moving this class later does not break old rows.
     */
    public function databaseType(object $notifiable): string
    {
        return 'enquiry.received';
    }
}
