<?php

use App\Support\EventDateFormatter;

// Anchor: 1735689600 = 2025-01-01 00:00:00 UTC
//   In Asia/Tokyo (UTC+9): 2025-01-01 09:00:00 JST  → "Wed, 01 Jan 2025 at 9:00 AM JST"
//   In America/New_York (EST = UTC−5): 2024-12-31 19:00:00 → "Tue, 31 Dec 2024 at 7:00 PM EST"
const EPOCH_2025 = 1735689600;

it('formats a single start time with the correct timezone abbreviation', function () {
    $result = EventDateFormatter::format(EPOCH_2025, null, 'Asia/Tokyo');
    expect($result)->toBe('Wed, 01 Jan 2025 at 9:00 AM JST');
});

it('formats a same-day range as start date with a time-only end', function () {
    $endsAt = EPOCH_2025 + 7200; // +2 h → 11:00 AM JST same day

    $result = EventDateFormatter::format(EPOCH_2025, $endsAt, 'Asia/Tokyo');
    expect($result)->toBe('Wed, 01 Jan 2025 at 9:00 AM JST – 11:00 AM');
});

it('formats a multi-day range with full dates on both ends', function () {
    $endsAt = EPOCH_2025 + 90000; // +25 h → 2025-01-02 10:00 AM JST

    $result = EventDateFormatter::format(EPOCH_2025, $endsAt, 'Asia/Tokyo');
    expect($result)->toBe('Wed, 01 Jan 2025 at 9:00 AM JST – Thu, 02 Jan 2025 at 10:00 AM JST');
});

it('returns the fallback string when starts_at is null', function () {
    expect(EventDateFormatter::format(null, null, 'UTC'))->toBe('Date to be confirmed');
});

it('ignores an end time that precedes the start time', function () {
    $result = EventDateFormatter::format(EPOCH_2025, EPOCH_2025 - 3600, 'Asia/Tokyo');
    expect($result)->toBe('Wed, 01 Jan 2025 at 9:00 AM JST');
});

it('uses the local date boundary when deciding same-day vs multi-day', function () {
    // 2025-01-01 00:00:00 UTC is still 2024-12-31 in New York (EST = UTC-5)
    // Start: 19:00 EST on 31 Dec 2024
    // End +6h: 2025-01-01 01:00 AM UTC = 2024-12-31 20:00 EST → still the same local day
    $endsAt = EPOCH_2025 + 3600; // +1 h in UTC → still 2024-12-31 in New York

    $result = EventDateFormatter::format(EPOCH_2025, $endsAt, 'America/New_York');
    expect($result)->toBe('Tue, 31 Dec 2024 at 7:00 PM EST – 8:00 PM');
});

it('crosses a local midnight boundary correctly', function () {
    // Start: 2025-01-01 23:00 UTC = 2025-01-02 08:00 JST (next day already in Tokyo)
    $startsAt = EPOCH_2025 + 23 * 3600; // 23:00 UTC → 08:00 JST next day
    $endsAt   = $startsAt + 7200;       // +2 h → 10:00 JST same (Jan 2) day

    $result = EventDateFormatter::format($startsAt, $endsAt, 'Asia/Tokyo');
    expect($result)->toBe('Thu, 02 Jan 2025 at 8:00 AM JST – 10:00 AM');
});
