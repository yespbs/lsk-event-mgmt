<x-mail::message>
# You're on the list, {{ $attendeeName }}!

You've successfully registered your interest for **{{ $eventName }}**. We're looking forward to seeing you there.

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

@if($eventDescription)
{{ $eventDescription }}
@endif

We'll send you a reminder **3 days before** and again **24 hours before** the event so you never miss a beat.

@if($locationLabel)
<x-mail::button :url="'https://www.google.com/maps/search/' . urlencode($locationLabel)">
View on Map
</x-mail::button>
@endif

See you there,

{{ config('app.name') }}

---
<small>You registered with this email address. If this wasn't you, you can safely ignore this message.</small>
</x-mail::message>
