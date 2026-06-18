<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('filters events by date_from and excludes earlier events', function () {
    $user = User::factory()->create();

    Event::factory()->for($user)->create(['created_time' => strtotime('2024-06-01')]); // excluded
    Event::factory()->for($user)->create(['created_time' => strtotime('2025-06-01')]); // included

    $this->getJson(route('events.data', ['date_from' => '2025-01-01']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.created_time', strtotime('2025-06-01'));
});

it('filters events by date_to and excludes later events', function () {
    $user = User::factory()->create();

    Event::factory()->for($user)->create(['created_time' => strtotime('2024-06-01')]); // included
    Event::factory()->for($user)->create(['created_time' => strtotime('2026-06-01')]); // excluded

    $this->getJson(route('events.data', ['date_to' => '2025-12-31']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.created_time', strtotime('2024-06-01'));
});

it('filters events within a date range using both date_from and date_to', function () {
    $user = User::factory()->create();

    Event::factory()->for($user)->create(['created_time' => strtotime('2023-12-31')]); // excluded
    Event::factory()->for($user)->create(['created_time' => strtotime('2024-06-15')]); // included
    Event::factory()->for($user)->create(['created_time' => strtotime('2025-01-01')]); // excluded

    $this->getJson(route('events.data', ['date_from' => '2024-01-01', 'date_to' => '2024-12-31']))
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('filters events by location using a bounding box around the city anchor', function () {
    $user = User::factory()->create();

    // Tokyo anchor: 35.6762, 139.6503 — jitter within ±0.5°
    Event::factory()->for($user)->create(['latitude' => 35.70, 'longitude' => 139.70]);

    // London: far outside Tokyo bounding box
    Event::factory()->for($user)->create(['latitude' => 51.5074, 'longitude' => -0.1278]);

    $this->getJson(route('events.data', ['location' => 'Tokyo, Japan']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.latitude', 35.70);
});

it('applies no location filter for an unknown city label', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->count(3)->create();

    // anchorFor() returns null → when() condition is falsy → no filter applied
    $this->getJson(route('events.data', ['location' => 'Atlantis, Ocean']))
        ->assertOk()
        ->assertJsonPath('total', 3);
});

it('filters events by status', function () {
    $user = User::factory()->create();
    Event::factory()->for($user)->create(['status' => 'published']);
    Event::factory()->for($user)->create(['status' => 'cancelled']);

    $this->getJson(route('events.data', ['status' => 'cancelled']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'cancelled');
});

it('combines status and date_from filters', function () {
    $user = User::factory()->create();

    Event::factory()->for($user)->create(['status' => 'published', 'created_time' => strtotime('2025-06-01')]);
    Event::factory()->for($user)->create(['status' => 'cancelled', 'created_time' => strtotime('2025-06-01')]);
    Event::factory()->for($user)->create(['status' => 'published', 'created_time' => strtotime('2024-01-01')]);

    $this->getJson(route('events.data', ['status' => 'published', 'date_from' => '2025-01-01']))
        ->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('data.0.status', 'published');
});
