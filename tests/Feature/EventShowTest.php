<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes event images as local asset URLs in sort order', function () {
    $event = Event::factory()->for(User::factory()->create())->create();
    $event->images()->createMany([
        ['filename' => 'placeholder-1.svg', 'sort_order' => 0],
        ['filename' => 'placeholder-2.svg', 'sort_order' => 1],
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('event.images.0', fn ($url) => str_ends_with($url, '/images/events/placeholder-1.svg'))
            ->where('event.images.1', fn ($url) => str_ends_with($url, '/images/events/placeholder-2.svg'))
        );
});

it('resolves a human-readable location label from coordinates', function () {
    $event = Event::factory()->for(User::factory()->create())->create([
        'latitude'  => 35.6762,  // Tokyo anchor
        'longitude' => 139.6503,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('event.location_label', 'Tokyo, Japan')
        );
});

it('resolves the correct IANA timezone from coordinates', function () {
    $event = Event::factory()->for(User::factory()->create())->create([
        'latitude'  => 35.6762,
        'longitude' => 139.6503,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('event.timezone', 'Asia/Tokyo')
        );
});

it('returns null location label and UTC timezone when coordinates are absent', function () {
    $event = Event::factory()->for(User::factory()->create())->create([
        'latitude'  => null,
        'longitude' => null,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('event.location_label', null)
            ->where('event.timezone', 'UTC')
        );
});

it('resolves a jittered coordinate to the correct city', function () {
    // Seeder applies ±0.5° jitter; confirm the bounding-box lookup is still accurate
    $event = Event::factory()->for(User::factory()->create())->create([
        'latitude'  => 35.70,  // Tokyo + 0.02°
        'longitude' => 139.60,
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('event.location_label', 'Tokyo, Japan')
        );
});

it('includes an accurate attendee count', function () {
    $event = Event::factory()->for(User::factory()->create())->create();
    $event->attendees()->createMany([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob',   'email' => 'bob@example.com'],
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('attendee_count', 2)
        );
});

it('returns an empty images array when the event has no images', function () {
    $event = Event::factory()->for(User::factory()->create())->create();

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('event.images', [])
        );
});
