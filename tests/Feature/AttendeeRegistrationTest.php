<?php

use App\Jobs\SendAttendeeConfirmation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('registers a new attendee and persists the record', function () {
    Queue::fake();
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertRedirect();

    $this->assertDatabaseHas('attendees', [
        'event_id' => $event->id,
        'name'     => 'Jane Doe',
        'email'    => 'jane@example.com',
    ]);
});

it('dispatches a confirmation job on successful registration', function () {
    Queue::fake();
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ]);

    Queue::assertPushed(SendAttendeeConfirmation::class, function ($job) {
        return $job->attendee->email === 'jane@example.com';
    });
});

it('rejects a duplicate email for the same event', function () {
    $event = Event::factory()->create();
    $event->attendees()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->post(route('events.attendees.store', $event), [
        'name'  => 'Jane Again',
        'email' => 'jane@example.com',
    ])->assertSessionHasErrors('email');

    $this->assertDatabaseCount('attendees', 1);
});

it('allows the same email on two different events', function () {
    Queue::fake();
    $eventA = Event::factory()->create();
    $eventB = Event::factory()->create();
    $eventA->attendees()->create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    $this->post(route('events.attendees.store', $eventB), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertRedirect();

    $this->assertDatabaseCount('attendees', 2);
});

it('validates that name and email are required', function () {
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [])
        ->assertSessionHasErrors(['name', 'email']);
});

it('validates that the email field contains a valid address', function () {
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});

it('returns the flash success message after registration', function () {
    Queue::fake();
    $event = Event::factory()->create();

    $this->post(route('events.attendees.store', $event), [
        'name'  => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertSessionHas('success');
});

it('includes the accurate attendee count on the event show page', function () {
    $event = Event::factory()->for(User::factory()->create())->create();
    $event->attendees()->createMany([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob',   'email' => 'bob@example.com'],
        ['name' => 'Carol', 'email' => 'carol@example.com'],
    ]);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Events/Show')
            ->where('attendee_count', 3)
        );
});
