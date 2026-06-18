# Rocky Events — Coding Challenge

A Laravel + Vue 3 (Inertia.js) application for browsing and managing events, built as a coding challenge.

## Project Overview

The task is to build two distinct visual layouts for browsing a large, globally-seeded event dataset, add image support, convert lat/lng to human-readable addresses, handle timezones, implement date/location filtering, and wire up attendee registration with confirmation and reminder emails.

See [CODING_TEST.md](CODING_TEST.md) for the full requirements.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Auth | Laravel Fortify (passkeys + 2FA) |
| Frontend bridge | Inertia.js v3 |
| Frontend | Vue 3 (TypeScript), Vite 8 |
| Styling | Tailwind CSS v4 |
| UI components | Reka UI + shadcn-style wrappers |
| Icons | Lucide Vue |
| Testing | Pest PHP, PHPStan, Pint, ESLint, Prettier |
| Database | SQLite (default), MySQL / PostgreSQL supported |
| Queue | Database driver (default) |
| Mail | Log driver (default, swap to SMTP/SES for production) |

## Project Structure

```
code/
├── app/
│   ├── Console/Commands/
│   │   └── SendEventReminders.php    # Scheduled reminder command (--hours=72|24)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AttendeeController.php  # Attendee registration
│   │   │   ├── EventController.php     # Listing, data API, show, visual pages
│   │   │   └── Settings/
│   │   └── Middleware/
│   ├── Jobs/
│   │   ├── SendAttendeeConfirmation.php  # Queued — on registration
│   │   └── SendAttendeeReminder.php      # Queued — dispatched by reminder command
│   ├── Mail/
│   │   ├── AttendeeConfirmation.php
│   │   └── AttendeeReminder.php
│   ├── Models/
│   │   ├── Attendee.php
│   │   ├── Event.php              # UUID PK, payload JSON cast
│   │   ├── EventImage.php         # url() helper → asset()
│   │   └── User.php
│   ├── Services/
│   │   └── LocationService.php    # Haversine nearest-neighbour → city label + IANA timezone
│   └── Support/
│       └── EventDateFormatter.php # Shared date formatter used by both jobs
├── database/
│   ├── factories/                 # EventFactory, UserFactory
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── EventSeeder.php        # Bulk-inserts up to 1.25 M events
│       └── EventImageSeeder.php   # 2 placeholder images per event
├── public/images/events/          # placeholder-{1,2,3}.svg — served locally
├── resources/
│   ├── js/
│   │   ├── components/
│   │   │   ├── EventFilters.vue      # Shared filter bar (status / date / location)
│   │   │   ├── RegisterInterest.vue  # Attendee registration form
│   │   │   └── ui/                   # Reka UI / shadcn-style primitives
│   │   ├── lib/date.ts               # Intl.DateTimeFormat wrappers
│   │   ├── types/event.ts            # Shared EventRow / EventFilters interfaces
│   │   └── pages/Events/
│   │       ├── Index.vue             # Table view with infinite scroll
│   │       ├── Show.vue              # Detail page — hero image, meta, registration
│   │       ├── VisualOne.vue         # Card grid (1→4 col, staggered animations)
│   │       └── VisualTwo.vue         # Vertical timeline, alternating, month dividers
│   └── views/emails/attendee/
│       ├── confirmation.blade.php
│       └── reminder.blade.php
├── routes/
│   ├── web.php
│   ├── settings.php
│   └── console.php                # Schedule::command() for both reminder windows
└── tests/
    ├── Feature/
    │   ├── AttendeeRegistrationTest.php
    │   ├── EventFiltersTest.php
    │   ├── EventListingTest.php
    │   ├── EventShowTest.php
    │   └── ReminderCommandTest.php
    └── Unit/
        ├── EventDateFormatterTest.php
        └── LocationServiceTest.php
```

## Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/` | `home` | Redirects to `/events` |
| GET | `/events` | `events.index` | Event list with infinite scroll and filters |
| GET | `/events/data` | `events.data` | JSON API — paginated events with stats |
| GET | `/events/{event}` | `events.show` | Detail page — images, meta, registration |
| POST | `/events/{event}/attendees` | `events.attendees.store` | Register attendee interest |
| GET | `/events-visual-1` | `events.visual1` | Card grid layout |
| GET | `/events-visual-2` | `events.visual2` | Timeline layout |
| GET | `/dashboard` | `dashboard` | Authenticated dashboard |

Auth routes (Fortify): `/login`, `/register`, `/forgot-password`, `/reset-password`, `/two-factor-challenge`, `/verify-email`.

Settings routes (auth-guarded): `/settings/profile`, `/settings/security`, `/settings/appearance`.

## Data Model

### Event

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | Primary key |
| `user_id` | FK → users | Cascade delete |
| `type` | string | concert, conference, meetup, workshop, festival, sports, networking, exhibition |
| `status` | string | draft, published, cancelled, sold_out |
| `created_time` | unix timestamp | Event start time (used for ordering) |
| `latitude` | decimal(10,7) | |
| `longitude` | decimal(10,7) | |
| `payload` | JSON | name, category, description, organizer, venue, schedule (starts_at/ends_at), pricing, tags |

The `payload` JSON structure:
```json
{
  "name": "Annual Jazz Festival",
  "category": "festival",
  "description": "Join us for Annual Jazz Festival — a festival you won't want to miss.",
  "organizer": { "name": "Organizer 42", "verified": true },
  "venue": { "name": "The Grand Arena", "capacity": "12000" },
  "location": { "lat": "40.7128", "lng": "-74.0060" },
  "schedule": { "starts_at": "1720000000", "ends_at": "1720086400" },
  "pricing": { "currency": "USD", "min_price": "49.99" },
  "tags": ["live", "in-person", "featured", "all-ages"]
}
```

## Features

- **Event list** — infinite-scroll table with status, date, and location filters; live stats (load time + payload size)
- **Event detail** — hero image with thumbnail switcher, meta row (date/timezone/location/venue/price), description, tags, registration sidebar
- **Visual layout 1** — responsive card grid (1→4 columns), staggered entrance animations, hover zoom
- **Visual layout 2** — vertical timeline, cards alternating left/right, month/year group dividers, two-image hover reveal
- **Image support** — `event_images` table, two images per event, SVG placeholders served from `public/images/events/`
- **Address resolution** — lat/lng → human-readable city label via Haversine nearest-neighbour lookup (no external API); IANA timezone included
- **Timezone-aware dates** — backend resolves timezone from coordinates; frontend uses `Intl.DateTimeFormat`; emails format dates in PHP with `DateTimeImmutable`
- **Filtering** — status, date range, and location (bounding-box query around city anchor); shared `EventFilters.vue` component
- **Attendee registration** — `attendees` table with per-event unique email guard; `RegisterInterest.vue` with loading/success states
- **Confirmation email** — queued `SendAttendeeConfirmation` job dispatched on registration; Laravel Markdown mailable
- **Reminder emails** — `events:send-reminders --hours=72|24` command with ±30-min window; scheduled hourly via `routes/console.php`
- **Authentication** — register, login, password reset, 2FA (TOTP), passkey support
- **Settings** — profile management, password change, account deletion, appearance (light/dark/system)
- **Large dataset** — seeder generates up to 1.25 M events across 67 global city anchors spanning ±1 year

## Local Setup

### Prerequisites

- PHP 8.3+
- Composer
- Node.js 20+ with pnpm (or npm)
- SQLite (bundled with PHP) or a MySQL/PostgreSQL instance

### Quick start

```bash
# Install all dependencies, generate app key, run migrations, build assets
composer run setup
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install          # or: pnpm install
npm run build
```

### Seed the database

```bash
# Default: 1,250,000 events + 2 placeholder images each
php artisan db:seed

# Lighter seed for local evaluation
SEED_ROWS=10000 php artisan db:seed
```

The seeder runs `EventSeeder` (events) followed by `EventImageSeeder` (images). Both use chunked bulk inserts so even the full 1.25 M row seed completes in under a minute on a modern machine.

### Development server

```bash
# Starts Laravel, queue worker, Pail log viewer, and Vite concurrently
composer run dev
```

Then open `http://localhost:8000`.

### Environment variables

Key variables in `.env` (see `.env.example` for the full list):

| Variable | Default | Description |
|---|---|---|
| `DB_CONNECTION` | `sqlite` | `sqlite`, `mysql`, or `pgsql` |
| `QUEUE_CONNECTION` | `database` | Switch to `redis` for production |
| `MAIL_MAILER` | `log` | Emails written to `storage/logs/laravel.log` by default |
| `SEED_ROWS` | `1250000` | Row count for `EventSeeder` |

## Development Scripts

### PHP / Composer

```bash
composer run dev          # Start all dev processes
composer run lint         # Auto-fix PHP style (Pint)
composer run lint:check   # Check PHP style without fixing
composer run types:check  # PHPStan static analysis
composer run test         # Lint + types + Pest tests
```

### JavaScript / npm

```bash
npm run dev               # Vite dev server
npm run build             # Production build
npm run lint              # ESLint auto-fix
npm run lint:check        # ESLint check only
npm run format            # Prettier auto-format
npm run format:check      # Prettier check only
npm run types:check       # vue-tsc type check
```

### CI check (runs everything)

```bash
composer run ci:check
```

## Testing

Tests use an in-memory SQLite database (`DB_DATABASE=:memory:`) with `QUEUE_CONNECTION=sync` and `MAIL_MAILER=array` so no external services are needed.

```bash
# Run the full Pest suite (85 tests)
php artisan test

# Run with verbose output — one line per test
php artisan test --verbose

# Run a specific test file
php artisan test tests/Feature/AttendeeRegistrationTest.php
php artisan test tests/Feature/EventFiltersTest.php
php artisan test tests/Feature/EventShowTest.php
php artisan test tests/Feature/ReminderCommandTest.php
php artisan test tests/Unit/LocationServiceTest.php
php artisan test tests/Unit/EventDateFormatterTest.php

# Run a single test by name (partial match)
php artisan test --filter "dispatches a confirmation job"

# Run only unit tests or only feature tests
php artisan test --testsuite Unit
php artisan test --testsuite Feature
```

### What's covered

| Suite | File | Focus |
|---|---|---|
| Feature | `EventListingTest` | Index page props, data API pagination, status filter |
| Feature | `AttendeeRegistrationTest` | Happy path, duplicate guard, validation, job dispatch, flash message |
| Feature | `EventFiltersTest` | `date_from`, `date_to`, date range, location bounding box, combined filters |
| Feature | `EventShowTest` | Image URLs, location label, timezone, null coords → UTC, attendee count |
| Feature | `ReminderCommandTest` | 72 h / 24 h windows, multi-attendee, out-of-window, draft exclusion, no-attendee skip |
| Unit | `LocationServiceTest` | Haversine nearest-neighbour, jitter tolerance, `anchorFor`, sorted city list |
| Unit | `EventDateFormatterTest` | Single time, same-day range, multi-day range, null fallback, midnight crossover |

### Static analysis and linting

```bash
composer run lint         # Auto-fix PHP style (Pint)
composer run types:check  # PHPStan static analysis
composer run ci:check     # Lint + PHPStan + Pest (what CI runs)
```

---

## Evaluating the app with Tinker

After running migrations and seeding, use Tinker to explore the data and trigger email flows without needing a browser:

```bash
php artisan tinker
```

### Check seeded data

```php
// Count events by status
Event::query()->selectRaw('status, count(*) as n')->groupBy('status')->get();

// Sample a random published event with its images
$event = Event::where('status', 'published')->with('images')->inRandomOrder()->first();
$event->payload['name'];       // event title
$event->images->map->url();    // image asset URLs
$event->latitude;              // coordinates
```

### Resolve a location and timezone

```php
$svc = app(App\Services\LocationService::class);

// Nearest city and IANA timezone for any coordinates
$svc->resolve(35.6762, 139.6503);
// → ['label' => 'Tokyo, Japan', 'city' => 'Tokyo', 'timezone' => 'Asia/Tokyo', ...]

// City anchor used by the bounding-box location filter
$svc->anchorFor('London, United Kingdom');
// → ['lat' => 51.5074, 'lng' => -0.1278]

// All city options (for the location dropdown)
count($svc->cities());
```

### Register an attendee and trigger the confirmation email

```php
$event = Event::where('status', 'published')->first();

// Create an attendee (triggers queued job; with QUEUE_CONNECTION=sync it runs immediately)
$attendee = $event->attendees()->create([
    'name'  => 'Ada Lovelace',
    'email' => 'ada@example.com',
]);

// Dispatch the confirmation email synchronously and inspect the log
App\Jobs\SendAttendeeConfirmation::dispatchSync($attendee);
// → check storage/logs/laravel.log for the rendered email
```

### Preview reminder emails

```php
// Pin an event to the 72-hour window and dispatch a test reminder
$event = Event::where('status', 'published')->first();
$event->update(['created_time' => now()->addHours(72)->getTimestamp()]);
$attendee = $event->attendees()->firstOrCreate(
    ['email' => 'test@example.com'],
    ['name'  => 'Test User']
);
App\Jobs\SendAttendeeReminder::dispatchSync($attendee, 72, '3 days');
// → check storage/logs/laravel.log
```

### Dry-run the reminder command

```php
// The scheduler picks events in a ±30-minute window around the target
// Artisan::call returns the exit code (0 = success)
Artisan::call('events:send-reminders', ['--hours' => 72]);
echo Artisan::output();
```

### Format an event date (the same logic used in emails)

```php
use App\Support\EventDateFormatter;
EventDateFormatter::format(1735689600, 1735696800, 'Asia/Tokyo');
// → "Wed, 01 Jan 2025 at 9:00 AM JST – 11:00 AM"
```

## Queue & Email

The queue connection defaults to `database`. Start a worker when testing attendee emails:

```bash
php artisan queue:listen --tries=1
```

Emails default to the `log` driver — check `storage/logs/laravel.log` to inspect outgoing mail during development. To use a real SMTP provider, update `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, and `MAIL_PASSWORD` in `.env`.

Reminder emails (3 days / 24 hours before event) should be dispatched via a scheduled command registered in `routes/console.php` and run by:

```bash
php artisan schedule:run   # or: php artisan schedule:work (dev)
```
