<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AttendeeReminder extends Mailable
{
    public function __construct(
        public readonly string $attendeeName,
        public readonly string $eventName,
        public readonly string $eventType,
        public readonly ?string $locationLabel,
        public readonly ?string $venueName,
        public readonly string $dateFormatted,
        public readonly string $reminderLabel,
        public readonly ?string $imageUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder ({$this->reminderLabel} away): {$this->eventName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.attendee.reminder',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
