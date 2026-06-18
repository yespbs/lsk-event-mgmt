<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Tag, Ticket } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import EventFilters from '@/components/EventFilters.vue';
import { Badge } from '@/components/ui/badge';
import { formatEventRange, toISOString } from '@/lib/date';
import type { EventFilters as EventFiltersType, EventRow } from '@/types';

const props = defineProps<{
    filters: { status: string | null; date_from: string | null; date_to: string | null; location: string | null };
    statuses: string[];
    cities: string[];
}>();

const form = reactive<EventFiltersType>({
    status: props.filters.status ?? '',
    date_from: props.filters.date_from ?? '',
    date_to: props.filters.date_to ?? '',
    location: props.filters.location ?? '',
});

const rows = ref<EventRow[]>([]);
const batchStart = ref(0); // index where the latest batch begins (for stagger)
const page = ref(0);
const lastPage = ref<number | null>(null);
const total = ref<number | null>(null);
const loading = ref(false);
const hasLoadedOnce = ref(false);
const sentinel = ref<HTMLElement | null>(null);
let observer: IntersectionObserver | null = null;

const hasMore = computed(() => lastPage.value === null || page.value < lastPage.value);

async function loadMore() {
    if (loading.value || !hasMore.value) return;
    loading.value = true;

    const params = new URLSearchParams({ page: String(page.value + 1) });
    if (form.status) params.set('status', form.status);
    if (form.date_from) params.set('date_from', form.date_from);
    if (form.date_to) params.set('date_to', form.date_to);
    if (form.location) params.set('location', form.location);

    try {
        const res = await fetch(`/events/data?${params}`, { headers: { Accept: 'application/json' } });
        const payload = await res.json();
        batchStart.value = rows.value.length;
        rows.value.push(...payload.data);
        page.value = payload.current_page;
        lastPage.value = payload.last_page;
        total.value = payload.total;
        hasLoadedOnce.value = true;
    } finally {
        loading.value = false;
    }
}

function applyFilters() {
    rows.value = [];
    batchStart.value = 0;
    page.value = 0;
    lastPage.value = null;
    total.value = null;
    hasLoadedOnce.value = false;
    loadMore();
}

type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';
const STATUS_VARIANTS: Record<string, BadgeVariant> = {
    published: 'default',
    cancelled: 'destructive',
    sold_out: 'secondary',
};
const statusVariant = (s: string): BadgeVariant => STATUS_VARIANTS[s] ?? 'outline';

function eventName(event: EventRow): string {
    return (event.payload as Record<string, unknown>).name as string ?? `Event ${event.id.slice(0, 8)}`;
}

function eventDescription(event: EventRow): string {
    return (event.payload as Record<string, unknown>).description as string ?? '';
}

function eventVenue(event: EventRow): string {
    const venue = (event.payload as Record<string, unknown>).venue as Record<string, unknown> | undefined;
    return venue?.name as string ?? '';
}

function eventPrice(event: EventRow): string {
    const pricing = (event.payload as Record<string, unknown>).pricing as Record<string, unknown> | undefined;
    if (!pricing) return '';
    const price = parseFloat(pricing.min_price as string);
    if (isNaN(price) || price === 0) return 'Free';
    return new Intl.NumberFormat(undefined, { style: 'currency', currency: pricing.currency as string ?? 'USD' }).format(price);
}

function eventSchedule(event: EventRow): { startsAt: number | null; endsAt: number | null } {
    const sched = (event.payload as Record<string, unknown>).schedule as Record<string, unknown> | undefined;
    return {
        startsAt: sched?.starts_at ? Number(sched.starts_at) : null,
        endsAt: sched?.ends_at ? Number(sched.ends_at) : null,
    };
}

// Stagger delay capped per batch (max 600 ms total spread)
function staggerDelay(index: number): string {
    const posInBatch = index - batchStart.value;
    return `${Math.min(posInBatch * 40, 600)}ms`;
}

onMounted(() => {
    observer = new IntersectionObserver((entries) => {
        if (entries[0]?.isIntersecting) loadMore();
    }, { rootMargin: '400px' });
    if (sentinel.value) observer.observe(sentinel.value);
    loadMore();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events · Visual 1" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Events</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
            </p>
        </div>

        <!-- Filters -->
        <EventFilters v-model="form" :statuses="statuses" :cities="cities" @apply="applyFilters" />

        <!-- Card grid -->
        <div
            v-if="hasLoadedOnce || loading"
            class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <Link
                v-for="(event, i) in rows"
                :key="event.id"
                :href="`/events/${event.id}`"
                class="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-sm
                       transition-all duration-300 hover:scale-[1.02] hover:shadow-xl
                       animate-in fade-in slide-in-from-bottom-4"
                :style="{ animationDelay: staggerDelay(i), animationFillMode: 'both', animationDuration: '400ms' }"
            >
                <!-- Image -->
                <div class="relative aspect-video overflow-hidden bg-muted">
                    <img
                        v-if="event.images[0]"
                        :src="event.images[0]"
                        :alt="eventName(event)"
                        class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div v-else class="flex h-full items-center justify-center text-muted-foreground">
                        <Tag :size="32" class="opacity-30" />
                    </div>

                    <!-- Status badge -->
                    <div class="absolute left-3 top-3">
                        <Badge :variant="statusVariant(event.status)" class="text-xs capitalize shadow">
                            {{ event.status.replace('_', ' ') }}
                        </Badge>
                    </div>

                    <!-- Type chip -->
                    <div class="absolute bottom-3 right-3">
                        <span class="rounded-full bg-black/60 px-2 py-0.5 text-xs capitalize text-white backdrop-blur-sm">
                            {{ event.type }}
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="flex flex-1 flex-col gap-2 p-4">
                    <h2 class="line-clamp-2 font-semibold leading-snug">{{ eventName(event) }}</h2>

                    <p class="line-clamp-2 text-xs text-muted-foreground">{{ eventDescription(event) }}</p>

                    <div class="mt-auto flex flex-col gap-1.5 pt-3 text-xs text-muted-foreground">
                        <!-- Date -->
                        <div class="flex items-start gap-1.5">
                            <CalendarDays :size="13" class="mt-0.5 shrink-0 text-primary" />
                            <time :datetime="toISOString(eventSchedule(event).startsAt)">
                                {{ formatEventRange(eventSchedule(event).startsAt, eventSchedule(event).endsAt, event.timezone) }}
                            </time>
                        </div>

                        <!-- Location -->
                        <div v-if="event.location_label" class="flex items-center gap-1.5">
                            <MapPin :size="13" class="shrink-0 text-primary" />
                            <span class="truncate">{{ event.location_label }}</span>
                            <span v-if="eventVenue(event)" class="truncate text-muted-foreground/70">
                                · {{ eventVenue(event) }}
                            </span>
                        </div>

                        <!-- Price -->
                        <div v-if="eventPrice(event)" class="flex items-center gap-1.5">
                            <Ticket :size="13" class="shrink-0 text-primary" />
                            <span class="font-medium text-foreground">{{ eventPrice(event) }}</span>
                        </div>
                    </div>
                </div>
            </Link>
        </div>

        <!-- Empty state -->
        <div
            v-if="hasLoadedOnce && !loading && rows.length === 0"
            class="flex flex-col items-center gap-3 py-24 text-center text-muted-foreground"
        >
            <Tag :size="40" class="opacity-30" />
            <p class="text-sm">No events match your filters.</p>
        </div>

        <!-- Infinite scroll sentinel -->
        <div ref="sentinel" class="h-1" />

        <!-- Loading indicator -->
        <div v-if="loading" class="flex justify-center py-6">
            <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>
    </div>
</template>
