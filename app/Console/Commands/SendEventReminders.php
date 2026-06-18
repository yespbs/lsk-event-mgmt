<?php

namespace App\Console\Commands;

use App\Jobs\SendAttendeeReminder;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Runs every hour via the scheduler. A ±30-minute window around the target
 * ensures each event falls into exactly one hourly window per reminder type,
 * preventing duplicate sends even when the scheduler fires slightly late.
 */
#[Signature('events:send-reminders {--hours=72 : Hours before the event (72 = 3-day, 24 = 24-hour)}')]
#[Description('Dispatch reminder emails to attendees of upcoming events')]
class SendEventReminders extends Command
{
    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        if (! \in_array($hours, [24, 72], true)) {
            $this->error("--hours must be 24 or 72, got {$hours}.");

            return self::FAILURE;
        }

        $label = $hours === 72 ? '3 days' : '24 hours';
        $now = now()->getTimestamp();
        $windowStart = $now + $hours * 3600 - 1800; // target − 30 min
        $windowEnd   = $now + $hours * 3600 + 1800; // target + 30 min

        $this->info("Sending {$label} reminders for events between "
            .date('Y-m-d H:i', $windowStart).' and '
            .date('Y-m-d H:i', $windowEnd).' UTC…');

        $dispatched = 0;
        $skipped = 0;

        Event::whereBetween('created_time', [$windowStart, $windowEnd])
            ->where('status', 'published')
            ->with(['attendees', 'images'])
            ->chunkById(100, function ($events) use ($label, $hours, &$dispatched, &$skipped) {
                foreach ($events as $event) {
                    if ($event->attendees->isEmpty()) {
                        $skipped++;
                        continue;
                    }

                    foreach ($event->attendees as $attendee) {
                        dispatch(new SendAttendeeReminder($attendee, $hours, $label));
                        $dispatched++;
                    }
                }
            });

        $this->info("Done — {$dispatched} reminder(s) dispatched, {$skipped} event(s) skipped (no attendees).");

        return self::SUCCESS;
    }
}
