<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, MapPin, Tag, Ticket } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import EventFilters from '@/components/EventFilters.vue';
import { Badge } from '@/components/ui/badge';
import { formatEventDate, toISOString } from '@/lib/date';
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
const batchStart = ref(0);
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

function getMonthKey(event: EventRow): string {
    const sched = (event.payload as Record<string, unknown>).schedule as Record<string, unknown> | undefined;
    const ts = sched?.starts_at ? Number(sched.starts_at) : event.created_time;
    if (!ts) return 'Unknown Date';
    return new Intl.DateTimeFormat('en', { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(ts * 1000);
}

function isNewMonth(event: EventRow, index: number): boolean {
    if (index === 0) return true;
    return getMonthKey(event) !== getMonthKey(rows.value[index - 1]!);
}

function eventName(event: EventRow): string {
    return (event.payload as Record<string, unknown>).name as string ?? `Event ${event.id.slice(0, 8)}`;
}

function eventDescription(event: EventRow): string {
    return (event.payload as Record<string, unknown>).description as string ?? '';
}

function eventVenue(event: EventRow): string {
    const venue = (event.payload as Record<string, unknown>).venue as Record<string, unknown> | undefined;
    return (venue?.name as string) ?? '';
}

function eventPrice(event: EventRow): string {
    const pricing = (event.payload as Record<string, unknown>).pricing as Record<string, unknown> | undefined;
    if (!pricing) return '';
    const price = parseFloat(pricing.min_price as string);
    if (isNaN(price) || price === 0) return 'Free';
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: (pricing.currency as string) ?? 'USD',
    }).format(price);
}

function eventStartsAt(event: EventRow): number | null {
    const sched = (event.payload as Record<string, unknown>).schedule as Record<string, unknown> | undefined;
    return sched?.starts_at ? Number(sched.starts_at) : null;
}

type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';
const STATUS_VARIANTS: Record<string, BadgeVariant> = {
    published: 'default',
    cancelled: 'destructive',
    sold_out: 'secondary',
};
const statusVariant = (s: string): BadgeVariant => STATUS_VARIANTS[s] ?? 'outline';

function staggerDelay(index: number): string {
    const posInBatch = index - batchStart.value;
    return `${Math.min(posInBatch * 50, 700)}ms`;
}

onMounted(() => {
    observer = new IntersectionObserver(
        (entries) => {
            if (entries[0]?.isIntersecting) loadMore();
        },
        { rootMargin: '400px' },
    );
    if (sentinel.value) observer.observe(sentinel.value);
    loadMore();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head title="Events · Timeline" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <!-- Header -->
        <div>
            <h1 class="text-2xl font-bold tracking-tight">Events Timeline</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ total !== null ? `${total.toLocaleString()} events` : 'Loading…' }}
            </p>
        </div>

        <!-- Filters -->
        <EventFilters v-model="form" :statuses="statuses" :cities="cities" @apply="applyFilters" />

        <!-- Timeline -->
        <div v-if="hasLoadedOnce || loading" class="relative py-4">
            <!-- Spine -->
            <div class="pointer-events-none absolute bottom-0 left-5 top-0 w-px bg-border md:left-1/2" />

            <template v-for="(event, i) in rows" :key="event.id">
                <!-- Month divider -->
                <div
                    v-if="isNewMonth(event, i)"
                    class="relative mb-6 mt-2 flex items-center justify-start md:justify-center"
                >
                    <span
                        class="relative z-10 ml-10 rounded-full border bg-background px-4 py-1 text-xs font-semibold tracking-wide text-muted-foreground shadow-sm md:ml-0"
                    >
                        {{ getMonthKey(event) }}
                    </span>
                </div>

                <!-- Event row -->
                <div
                    class="relative mb-5 flex pl-14 md:pl-0"
                    :class="i % 2 === 0 ? 'md:pr-[calc(50%+1.5rem)]' : 'md:pl-[calc(50%+1.5rem)]'"
                >
                    <!-- Spine dot -->
                    <div
                        class="absolute left-5 top-5 z-10 h-3 w-3 -translate-x-1/2 rounded-full border-2 border-primary bg-background shadow-sm transition-transform duration-300 group-hover:scale-150 md:left-1/2"
                    />

                    <!-- Card -->
                    <Link
                        :href="`/events/${event.id}`"
                        class="group w-full overflow-hidden rounded-xl border bg-card shadow-sm transition-all duration-300 hover:scale-[1.015] hover:shadow-xl animate-in fade-in"
                        :class="i % 2 === 0 ? 'md:slide-in-from-left-8' : 'md:slide-in-from-right-8'"
                        :style="{
                            animationDelay: staggerDelay(i),
                            animationFillMode: 'both',
                            animationDuration: '450ms',
                        }"
                    >
                        <div class="flex gap-4 p-4">
                            <!-- Thumbnail -->
                            <div class="relative h-18 w-18 shrink-0 overflow-hidden rounded-lg bg-muted">
                                <img
                                    v-if="event.images[0]"
                                    :src="event.images[0]"
                                    :alt="eventName(event)"
                                    class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-110"
                                    loading="lazy"
                                />
                                <div v-else class="flex h-full items-center justify-center">
                                    <Tag :size="18" class="opacity-20" />
                                </div>
                                <!-- Second image peek -->
                                <img
                                    v-if="event.images[1]"
                                    :src="event.images[1]"
                                    :alt="eventName(event)"
                                    class="absolute inset-0 h-full w-full object-cover opacity-0 transition-all duration-500 group-hover:opacity-100 group-hover:scale-110"
                                    loading="lazy"
                                />
                            </div>

                            <!-- Content -->
                            <div class="flex min-w-0 flex-1 flex-col gap-1">
                                <div class="flex items-start justify-between gap-2">
                                    <h3 class="line-clamp-1 font-semibold leading-snug">{{ eventName(event) }}</h3>
                                    <Badge :variant="statusVariant(event.status)" class="shrink-0 text-xs capitalize">
                                        {{ event.status.replace('_', ' ') }}
                                    </Badge>
                                </div>

                                <p class="line-clamp-1 text-xs text-muted-foreground">{{ eventDescription(event) }}</p>

                                <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span class="flex items-center gap-1">
                                        <CalendarDays :size="11" class="shrink-0 text-primary" />
                                        <time :datetime="toISOString(eventStartsAt(event))">
                                            {{ formatEventDate(eventStartsAt(event), event.timezone) }}
                                        </time>
                                    </span>

                                    <span v-if="event.location_label" class="flex items-center gap-1">
                                        <MapPin :size="11" class="shrink-0 text-primary" />
                                        <span class="truncate">{{ event.location_label }}</span>
                                    </span>

                                    <span v-if="eventPrice(event)" class="flex items-center gap-1">
                                        <Ticket :size="11" class="shrink-0 text-primary" />
                                        {{ eventPrice(event) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer strip -->
                        <div class="flex items-center justify-between border-t bg-muted/30 px-4 py-1.5 text-xs capitalize text-muted-foreground">
                            <span>{{ event.type }}</span>
                            <span v-if="eventVenue(event)" class="truncate pl-4 text-right text-muted-foreground/60">
                                {{ eventVenue(event) }}
                            </span>
                        </div>
                    </Link>
                </div>
            </template>
        </div>

        <!-- Empty state -->
        <div
            v-if="hasLoadedOnce && !loading && rows.length === 0"
            class="flex flex-col items-center gap-3 py-24 text-center text-muted-foreground"
        >
            <Tag :size="40" class="opacity-30" />
            <p class="text-sm">No events match your filters.</p>
        </div>

        <!-- Sentinel -->
        <div ref="sentinel" class="h-1" />

        <!-- Spinner -->
        <div v-if="loading" class="flex justify-center py-6">
            <div class="h-6 w-6 animate-spin rounded-full border-2 border-primary border-t-transparent" />
        </div>
    </div>
</template>
