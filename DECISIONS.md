# Decisions

A short note on the choices made building this out.

---

## Image storage

Images are stored on the local filesystem under `public/images/events/` and served via Laravel's `asset()` helper. The coding test explicitly requires locally-served images, so no cloud storage was introduced.

Three distinct SVG placeholder files (`placeholder-1.svg` through `placeholder-3.svg`) cover purple/indigo, amber/red, and green/cyan colour themes. The seeder assigns two images per event by cycling through these three files, so every event consistently has `images[0]` and `images[1]` without duplicating file storage.

The `event_images` table carries a `sort_order` column and the relationship is ordered by it, so image priority is deterministic and controllable without touching filenames.

**Alternative considered:** storing image URLs directly on the event payload. Rejected because it conflates storage with display, makes reordering harder, and scatters the image concern across the schema.

---

## Address resolution

Events have lat/lng but no human-readable location. All 67 city anchors in the seeder are known up front, so a local nearest-neighbour lookup using the Haversine formula is always accurate — the seeder applies ±0.5° jitter, and the lookup finds the right city every time without an external API call.

`LocationService` holds the 67 anchors (each with city, country, and IANA timezone), computes great-circle distance for every candidate, and returns the closest one. The bounding-box filter in `EventController` uses the same anchors: a city selection maps to anchor coordinates, and a ±0.6° box (slightly wider than the seeder's jitter) matches all events in that city.

**Alternative considered:** a geocoding API (Nominatim, Google Maps). Rejected — adds latency, rate limits, and an external dependency. Because the data is fully synthetic and bounded to 67 known points, local lookup is strictly better here.

---

## Timezone strategy

The backend resolves the IANA timezone (e.g. `Asia/Tokyo`, `America/Los_Angeles`) from the event's coordinates via `LocationService` and returns it as a field on every formatted event. This is the one place timezones are looked up.

The frontend consumes that timezone string directly with `Intl.DateTimeFormat`, which is available in every modern browser at zero bundle cost. All date formatters live in `resources/js/lib/date.ts` and accept an optional timezone argument, defaulting to `'UTC'`. This means "8 PM in Tokyo" is what a Tokyo attendee sees, not a UTC offset.

Emails format dates server-side using PHP's `DateTimeImmutable` with a `DateTimeZone` constructed from the same IANA string, so the email and the page show the same time.

**Alternative considered:** storing the timezone on the event row. Rejected — it would duplicate data already derivable from lat/lng, and keeping a single source of truth avoids drift if city data is ever updated.

**Alternative considered:** a JS date library (date-fns, Luxon). Rejected — `Intl.DateTimeFormat` handles every case needed here (locale-aware weekday names, timezone abbreviations, 12-hour clock) without adding a dependency.

---

## Layout choices

**Visual 1 — Card grid**

A responsive grid that steps from 1 → 2 → 3 → 4 columns as the viewport widens. Each card leads with a 16:9 hero image, a status badge overlaid in the corner, and a type chip. Cards scale up on hover (`hover:scale-[1.02]`) to signal interactivity without distracting from content. Entrance animations use `animate-in fade-in slide-in-from-bottom-4` with per-card stagger capped at 600 ms so fast scrollers don't sit waiting for everything to arrive.

**Visual 2 — Timeline**

A vertical spine with cards alternating left and right on desktop and collapsing to a left-anchored single column on mobile. Events are grouped by month so the chronological shape of the data is immediately legible. Each card shows a 72×72 thumbnail from `images[0]`; hovering reveals `images[1]` fading in underneath — a subtle differentiation from V1's full hero image. Stagger is slightly slower (50 ms/card, cap 700 ms) because timeline cards carry more text and benefit from a little more breathing room.

The two layouts are deliberately different in their primary axis: V1 is spatial (scan across rows), V2 is temporal (read down a spine). Filters are shared between them because the data model is the same.

---

## Attendee registration and emails

Duplicate registration is caught with an explicit `exists()` check before `create()`, which returns a readable validation message (`"This email is already registered for this event."`). The alternative — catching the `UniqueConstraintViolationException` from the database — would work but leaks persistence details into controller logic and is harder to unit-test.

Confirmation and reminder emails are queued jobs so the HTTP response is never blocked by mail sending. The job holds the `Attendee` model (serialized by `SerializesModels`) and injects `LocationService` through `handle()` dependency injection — the service is resolved fresh by the queue worker, never serialized to the jobs table.

Reminder scheduling uses a single `events:send-reminders --hours=` command rather than two separate commands. A ±30-minute window around the target (72 h or 24 h) means the hourly scheduler hits each event in exactly one window per reminder type — no duplicates, no gaps.

---

## Build tooling fix

Reka UI nests `@vueuse/core@14.3.0` while the project declares `@vueuse/core@12.8.2`. Vite 8 uses Rolldown as its bundler, which is stricter than Rollup about `#__PURE__` annotation placement and emits `INVALID_ANNOTATION` warnings when two versions of the same package end up in the bundle. Adding `resolve.dedupe: ['@vueuse/core']` to `vite.config.ts` forces all consumers to share the top-level version, eliminating the warning without touching `node_modules`.
