<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\LocationService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    /** Degrees of latitude/longitude padding around each city anchor. */
    private const LOCATION_RADIUS_DEG = 0.6;

    public function __construct(private readonly LocationService $location) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status'    => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to'   => $request->input('date_to'),
                'location'  => $request->input('location'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
            'cities'   => $this->location->cities(),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        $items = collect($events->items())->map(fn ($event) => $this->formatEvent($event));

        return response()->json([
            'data'         => $items,
            'current_page' => $events->currentPage(),
            'last_page'    => $events->lastPage(),
            'total'        => $events->total(),
            'stats'        => $stats,
        ]);
    }

    public function visualOne(Request $request): Response
    {
        return Inertia::render('Events/VisualOne', $this->sharedPageProps($request));
    }

    public function visualTwo(Request $request): Response
    {
        return Inertia::render('Events/VisualTwo', $this->sharedPageProps($request));
    }

    public function show(Event $event): Response
    {
        $event->load(['user', 'images']);

        return Inertia::render('Events/Show', [
            'event'          => $this->formatEvent($event),
            'attendee_count' => $event->attendees()->count(),
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $dateFrom = $request->input('date_from')
            ? strtotime($request->input('date_from'))
            : null;

        $dateTo = $request->input('date_to')
            ? strtotime($request->input('date_to') . ' 23:59:59')
            : null;

        $locationAnchor = $request->input('location')
            ? $this->location->anchorFor($request->input('location'))
            : null;

        $events = Event::with(['user', 'images'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($dateFrom, fn ($q) => $q->where('created_time', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->where('created_time', '<=', $dateTo))
            ->when($locationAnchor, function ($q) use ($locationAnchor) {
                $r = self::LOCATION_RADIUS_DEG;
                $q->whereBetween('latitude', [$locationAnchor['lat'] - $r, $locationAnchor['lat'] + $r])
                  ->whereBetween('longitude', [$locationAnchor['lng'] - $r, $locationAnchor['lng'] + $r]);
            })
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms'    => (int) round((microtime(true) - $start) * 1000),
            'bytes' => \strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }

    /** @return array<string, mixed> */
    private function sharedPageProps(Request $request): array
    {
        return [
            'filters' => [
                'status'    => $request->input('status'),
                'date_from' => $request->input('date_from'),
                'date_to'   => $request->input('date_to'),
                'location'  => $request->input('location'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
            'cities'   => $this->location->cities(),
        ];
    }

    /** @return array<string, mixed> */
    private function formatEvent(Event $event): array
    {
        $location = ($event->latitude !== null && $event->longitude !== null)
            ? $this->location->resolve($event->latitude, $event->longitude)
            : null;

        return [
            ...$event->toArray(),
            'images'         => $event->images->map(fn ($img) => $img->url())->values(),
            'location_label' => $location['label'] ?? null,
            'timezone'       => $location['timezone'] ?? 'UTC',
        ];
    }
}
