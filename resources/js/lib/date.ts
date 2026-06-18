/**
 * Date/time formatting for global events.
 *
 * Strategy: display times in the event's local timezone (resolved server-side
 * from lat/lng). This is the most meaningful representation — "the concert
 * starts at 8 PM in Tokyo" matters more than the viewer's local clock.
 * The timezone abbreviation (e.g. JST, CET, PDT) is shown alongside the time
 * so the viewer always knows which zone they're reading.
 */

const DATE_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};

const TIME_FORMAT: Intl.DateTimeFormatOptions = {
    hour: 'numeric',
    minute: '2-digit',
    timeZoneName: 'short',
};

const DATETIME_FORMAT: Intl.DateTimeFormatOptions = {
    ...DATE_FORMAT,
    ...TIME_FORMAT,
};

function fmt(options: Intl.DateTimeFormatOptions, timezone: string) {
    return new Intl.DateTimeFormat(undefined, { ...options, timeZone: timezone });
}

/** "Sat, 21 Jun 2025 · 8:00 PM JST" */
export function formatEventDate(unixSeconds: number | null | undefined, timezone = 'UTC'): string {
    if (unixSeconds == null) return '—';
    const ms = unixSeconds * 1000;
    const parts = fmt(DATETIME_FORMAT, timezone).formatToParts(ms);

    const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';

    const weekday = get('weekday');
    const day = get('day');
    const month = get('month');
    const year = get('year');
    const hour = get('hour');
    const minute = get('minute');
    const dayPeriod = get('dayPeriod');
    const tz = get('timeZoneName');

    const time = dayPeriod ? `${hour}:${minute} ${dayPeriod}` : `${hour}:${minute}`;
    return `${weekday}, ${day} ${month} ${year} · ${time} ${tz}`.trim();
}

/** "8:00 PM JST" — just the time portion, for use alongside a date */
export function formatEventTime(unixSeconds: number | null | undefined, timezone = 'UTC'): string {
    if (unixSeconds == null) return '—';
    return fmt(TIME_FORMAT, timezone).format(unixSeconds * 1000);
}

/** "Sat, 21 Jun 2025" */
export function formatEventDay(unixSeconds: number | null | undefined, timezone = 'UTC'): string {
    if (unixSeconds == null) return '—';
    return fmt(DATE_FORMAT, timezone).format(unixSeconds * 1000);
}

/**
 * Human-readable duration range.
 * Same day  → "Sat, 21 Jun 2025 · 8:00 PM – 11:00 PM JST"
 * Multi-day → "Sat, 21 Jun – Sun, 22 Jun 2025 · 8:00 PM JST"
 */
export function formatEventRange(
    startsAt: number | null | undefined,
    endsAt: number | null | undefined,
    timezone = 'UTC',
): string {
    if (startsAt == null) return '—';

    const startDate = formatEventDay(startsAt, timezone);
    const startTime = formatEventTime(startsAt, timezone);

    if (endsAt == null) return `${startDate} · ${startTime}`;

    const endDate = formatEventDay(endsAt, timezone);
    const endTime = formatEventTime(endsAt, timezone);

    if (startDate === endDate) {
        // Strip timezone from start time when shown inline with end time
        const startTimeNoTz = startTime.replace(/\s+\w+$/, '');
        return `${startDate} · ${startTimeNoTz} – ${endTime}`;
    }

    return `${startDate} – ${endDate} · ${startTime}`;
}

/** ISO 8601 string for <time datetime="…"> attributes */
export function toISOString(unixSeconds: number | null | undefined): string {
    if (unixSeconds == null) return '';
    return new Date(unixSeconds * 1000).toISOString();
}
