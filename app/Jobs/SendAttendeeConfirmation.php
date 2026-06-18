<?php

namespace App\Jobs;

use App\Mail\AttendeeConfirmation;
use App\Models\Attendee;
use App\Services\LocationService;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAttendeeConfirmation implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Attendee $attendee) {}

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

        Mail::to($attendee->email)->send(new AttendeeConfirmation(
            attendeeName: $attendee->name,
            eventName: $payload['name'] ?? "Event {$event->id}",
            eventDescription: $payload['description'] ?? '',
            eventType: $event->type,
            locationLabel: $locationInfo['label'] ?? null,
            venueName: ($payload['venue'] ?? [])['name'] ?? null,
            dateFormatted: $this->formatDate($startsAt, $endsAt, $timezone),
            timezone: $timezone,
            imageUrl: $event->images->first()?->url(),
        ));
    }

    private function formatDate(?int $startsAt, ?int $endsAt, string $timezone): string
    {
        if (! $startsAt) {
            return 'Date to be confirmed';
        }

        $tz = new DateTimeZone($timezone);
        $start = (new DateTimeImmutable())->setTimestamp($startsAt)->setTimezone($tz);
        $formatted = $start->format('D, d M Y \a\t g:i A T');

        if ($endsAt && $endsAt > $startsAt) {
            $end = (new DateTimeImmutable())->setTimestamp($endsAt)->setTimezone($tz);
            if ($start->format('Y-m-d') === $end->format('Y-m-d')) {
                $formatted .= ' – ' . $end->format('g:i A');
            } else {
                $formatted .= ' – ' . $end->format('D, d M Y \a\t g:i A T');
            }
        }

        return $formatted;
    }
}
