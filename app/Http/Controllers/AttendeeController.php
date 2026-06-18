<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendeeController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $alreadyRegistered = $event->attendees()
            ->where('email', $validated['email'])
            ->exists();

        if ($alreadyRegistered) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered for this event.',
            ]);
        }

        $attendee = $event->attendees()->create($validated);

        // Confirmation email dispatched in T8
        // dispatch(new \App\Jobs\SendAttendeeConfirmation($attendee));

        return back()->with('success', "You're on the list, {$attendee->name}! Check your inbox for a confirmation.");
    }
}
