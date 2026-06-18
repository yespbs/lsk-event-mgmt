<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

class EventDateFormatter
{
    public static function format(?int $startsAt, ?int $endsAt, string $timezone): string
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
                $formatted .= ' – '.$end->format('g:i A');
            } else {
                $formatted .= ' – '.$end->format('D, d M Y \a\t g:i A T');
            }
        }

        return $formatted;
    }
}
