<x-mail::message>
# Coming up in {{ $reminderLabel }}: {{ $eventName }}

Hi {{ $attendeeName }}, this is your {{ $reminderLabel }} reminder for an event you're registered for. We'll see you soon!

@if($imageUrl)
<x-mail::panel>
![{{ $eventName }}]({{ $imageUrl }})
</x-mail::panel>
@endif

<x-mail::table>
| | |
|:--|:--|
| **Date & Time** | {{ $dateFormatted }} |
| **Type** | {{ ucfirst($eventType) }} |
@if($locationLabel)
| **Location** | {{ $locationLabel }} |
@endif
@if($venueName)
| **Venue** | {{ $venueName }} |
@endif
</x-mail::table>

@if($locationLabel)
<x-mail::button :url="'https://www.google.com/maps/search/' . urlencode($locationLabel)">
View on Map
</x-mail::button>
@endif

See you there,

{{ config('app.name') }}

---
<small>You're receiving this because you registered for this event. Questions? Reply to this email.</small>
</x-mail::message>
