<?php

use App\Jobs\SendAttendeeReminder;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('dispatches 3-day reminder jobs for attendees of events in the 72-hour window', function () {
    Queue::fake();

    $event = Event::factory()->create([
        'status'       => 'published',
        'created_time' => now()->addHours(72)->getTimestamp(),
    ]);
    $event->attendees()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    $this->artisan('events:send-reminders', ['--hours' => 72])
        ->assertSuccessful();

    Queue::assertPushed(SendAttendeeReminder::class, 1);
    Queue::assertPushed(SendAttendeeReminder::class, fn ($job) =>
        $job->hours === 72 && $job->reminderLabel === '3 days'
    );
});

it('dispatches 24-hour reminder jobs for attendees of events in the 24-hour window', function () {
    Queue::fake();

    $event = Event::factory()->create([
        'status'       => 'published',
        'created_time' => now()->addHours(24)->getTimestamp(),
    ]);
    $event->attendees()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->artisan('events:send-reminders', ['--hours' => 24])
        ->assertSuccessful();

    Queue::assertPushed(SendAttendeeReminder::class, 1);
    Queue::assertPushed(SendAttendeeReminder::class, fn ($job) =>
        $job->hours === 24 && $job->reminderLabel === '24 hours'
    );
});

it('dispatches one job per attendee when multiple attendees are registered', function () {
    Queue::fake();

    $event = Event::factory()->create([
        'status'       => 'published',
        'created_time' => now()->addHours(72)->getTimestamp(),
    ]);
    $event->attendees()->createMany([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob',   'email' => 'bob@example.com'],
        ['name' => 'Carol', 'email' => 'carol@example.com'],
    ]);

    $this->artisan('events:send-reminders', ['--hours' => 72])
        ->assertSuccessful();

    Queue::assertPushed(SendAttendeeReminder::class, 3);
});

it('does not dispatch jobs for events outside the reminder window', function () {
    Queue::fake();

    // 74 hours away — outside the ±30-minute window around 72 h
    $event = Event::factory()->create([
        'status'       => 'published',
        'created_time' => now()->addHours(74)->getTimestamp(),
    ]);
    $event->attendees()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    $this->artisan('events:send-reminders', ['--hours' => 72])
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('does not dispatch jobs for unpublished events', function () {
    Queue::fake();

    $event = Event::factory()->create([
        'status'       => 'draft',
        'created_time' => now()->addHours(72)->getTimestamp(),
    ]);
    $event->attendees()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    $this->artisan('events:send-reminders', ['--hours' => 72])
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('skips events that have no attendees and reports them in the output', function () {
    Queue::fake();

    Event::factory()->create([
        'status'       => 'published',
        'created_time' => now()->addHours(72)->getTimestamp(),
    ]);

    $this->artisan('events:send-reminders', ['--hours' => 72])
        ->assertSuccessful()
        ->expectsOutputToContain('1 event(s) skipped');

    Queue::assertNothingPushed();
});

it('exits with a failure code for an invalid hours value', function () {
    $this->artisan('events:send-reminders', ['--hours' => 48])
        ->assertFailed();
});
