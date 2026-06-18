<?php

namespace App\Jobs;

use App\Mail\AttendeeReminder;
use App\Models\Attendee;
use App\Services\LocationService;
use App\Support\EventDateFormatter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAttendeeReminder implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Attendee $attendee,
        public readonly int $hours,
        public readonly string $reminderLabel,
    ) {}

    public function handle(LocationService $location): void
    {
        $attendee = $this->attendee;
        $event = $attendee->event()->with('images')->first();

        if (! $event) {
            return;
        }

        $payload = $event->payload;
        $sched = $payload['schedule'] ?? [];
        $startsAt = isset($sched['starts_at']) ? (int) $sched['starts_at'] : null;
        $endsAt = isset($sched['ends_at']) ? (int) $sched['ends_at'] : null;

        $locationInfo = ($event->latitude !== null && $event->longitude !== null)
            ? $location->resolve($event->latitude, $event->longitude)
            : null;

        $timezone = $locationInfo['timezone'] ?? 'UTC';

        Mail::to($attendee->email)->send(new AttendeeReminder(
            attendeeName: $attendee->name,
            eventName: $payload['name'] ?? "Event {$event->id}",
            eventType: $event->type,
            locationLabel: $locationInfo['label'] ?? null,
            venueName: ($payload['venue'] ?? [])['name'] ?? null,
            dateFormatted: EventDateFormatter::format($startsAt, $endsAt, $timezone),
            reminderLabel: $this->reminderLabel,
            imageUrl: $event->images->first()?->url(),
        ));
    }

}
