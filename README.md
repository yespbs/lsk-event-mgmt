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
│   ├── Actions/Fortify/          # User creation & password reset actions
│   ├── Concerns/                 # Shared validation rule traits
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── EventController.php   # Events listing, data API, detail
│   │   │   └── Settings/             # Profile, security controllers
│   │   ├── Middleware/               # Appearance, Inertia shared data
│   │   └── Requests/Settings/        # Form request classes
│   ├── Models/
│   │   ├── Event.php                 # UUID primary key, payload JSON cast
│   │   └── User.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── FortifyServiceProvider.php
├── database/
│   ├── factories/                    # EventFactory, UserFactory
│   ├── migrations/                   # Users, cache, jobs, passkeys, events, 2FA
│   └── seeders/
│       ├── DatabaseSeeder.php
│       └── EventSeeder.php           # Bulk-inserts up to 1.25M events
├── resources/
│   ├── css/app.css
│   └── js/
│       ├── app.ts                    # Inertia bootstrap
│       ├── components/               # Shared app components
│       │   └── ui/                   # alert, avatar, badge, breadcrumb, button,
│       │                             # card, checkbox, collapsible, dialog,
│       │                             # dropdown-menu, input, input-otp, label,
│       │                             # navigation-menu, select, separator, sheet, …
│       └── pages/
│           ├── Events/
│           │   ├── Index.vue         # Table view — infinite scroll, status + date filters
│           │   ├── Show.vue          # Raw event detail (payload JSON)
│           │   ├── VisualOne.vue     # [stub] Visual layout 1
│           │   └── VisualTwo.vue     # [stub] Visual layout 2
│           ├── auth/                 # Login, register, forgot/reset password, 2FA, verify email
│           ├── settings/             # Profile, security, appearance
│           ├── Dashboard.vue
│           └── Welcome.vue
├── routes/
│   ├── web.php                       # Public + event routes
│   ├── settings.php                  # Authenticated settings routes
│   └── console.php                   # Artisan schedule / console commands
└── config/                           # Standard Laravel config files
```

## Routes

| Method | URI | Name | Description |
|---|---|---|---|
| GET | `/` | `home` | Redirects to `/events` |
| GET | `/events` | `events.index` | Event list (Inertia page) |
| GET | `/events/data` | `events.data` | JSON API — paginated events with stats |
| GET | `/events/{event}` | `events.show` | Event detail page |
| GET | `/events-visual-1` | `events.visual1` | Visual layout 1 (stub) |
| GET | `/events-visual-2` | `events.visual2` | Visual layout 2 (stub) |
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

### Implemented

- **Event list** — infinite-scroll table with status and date filters, live stats (load time + payload size)
- **Event detail** — raw payload viewer
- **Authentication** — register, login, password reset, 2FA (TOTP), passkey support
- **Settings** — profile management, password change, account deletion, appearance (light/dark/system)
- **Large dataset** — seeder generates up to 1.25 M events across ~80 global city anchors spanning one year past to one year future

### To Be Built (Coding Test)

- **Event Visuals 1 & 2** — two distinct browsing layouts (e.g. card grid + timeline / map)
- **Image support** — 2+ images per event, stored and served locally
- **Human-readable addresses** — reverse geocode lat/lng
- **Timezone-aware date/time** — sensible display for global events
- **Filtering** — by date range and location
- **Animations** — tasteful, Tailwind-based
- **Attendee registration** — interest/attendance list per event
- **Email confirmation** — sent when attendee is added
- **Reminder emails** — scheduled 3 days and 24 hours before event start

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
# Default: 1,250,000 events
php artisan db:seed

# Custom row count
SEED_ROWS=50000 php artisan db:seed
```

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

```bash
php artisan test          # Run Pest test suite
```

Tests live in `tests/`. PHPStan config is in `phpstan.neon`, Pest config in `phpunit.xml`.

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
