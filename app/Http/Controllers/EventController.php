<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Events/Index', [
            'filters' => [
                'status' => $request->input('status'),
                'from' => $request->input('from', '2023-01-01'),
            ],
            'statuses' => ['draft', 'published', 'cancelled', 'sold_out'],
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        [$events, $stats] = $this->loadListing($request);

        $items = collect($events->items())->map(fn ($event) => array_merge(
            $event->toArray(),
            ['images' => $event->images->map(fn ($img) => $img->url())->values()]
        ));

        return response()->json([
            'data' => $items,
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'total' => $events->total(),
            'stats' => $stats,
        ]);
    }

    public function show(Event $event): Response
    {
        $event->load(['user', 'images']);

        return Inertia::render('Events/Show', [
            'event' => array_merge(
                $event->toArray(),
                ['images' => $event->images->map(fn ($img) => $img->url())->values()]
            ),
        ]);
    }

    /**
     * @return array{0: LengthAwarePaginator, 1: array{ms: int, bytes: int}}
     */
    private function loadListing(Request $request): array
    {
        $start = microtime(true);

        $events = Event::with(['user', 'images'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('created_time')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'ms' => (int) round((microtime(true) - $start) * 1000),
            'bytes' => \strlen((string) json_encode($events->items())),
        ];

        return [$events, $stats];
    }
}
