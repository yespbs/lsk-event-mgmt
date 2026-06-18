<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, MapPin, Tag, Ticket } from '@lucide/vue';
import { ref } from 'vue';
import RegisterInterest from '@/components/RegisterInterest.vue';
import { Badge } from '@/components/ui/badge';
import { formatEventRange, toISOString } from '@/lib/date';
import type { EventRow } from '@/types';

const props = defineProps<{
    event: EventRow;
    attendee_count: number;
}>();

type BadgeVariant = 'default' | 'outline' | 'destructive' | 'secondary';
const STATUS_VARIANTS: Record<string, BadgeVariant> = {
    published: 'default',
    cancelled: 'destructive',
    sold_out: 'secondary',
};
const statusVariant = (s: string): BadgeVariant => STATUS_VARIANTS[s] ?? 'outline';

const payload = props.event.payload as Record<string, unknown>;
const name = payload.name as string ?? `Event ${props.event.id.slice(0, 8)}`;
const description = payload.description as string ?? '';
const venue = (payload.venue as Record<string, unknown> | undefined)?.name as string ?? '';
const schedule = payload.schedule as Record<string, unknown> | undefined;
const startsAt = schedule?.starts_at ? Number(schedule.starts_at) : null;
const endsAt = schedule?.ends_at ? Number(schedule.ends_at) : null;
const pricing = payload.pricing as Record<string, unknown> | undefined;
const priceRaw = pricing ? parseFloat(pricing.min_price as string) : NaN;
const price = isNaN(priceRaw) || priceRaw === 0
    ? 'Free'
    : new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: (pricing?.currency as string) ?? 'USD',
    }).format(priceRaw);
const tags = (payload.tags as string[] | undefined) ?? [];

const activeImage = ref(0);
</script>

<template>
    <Head :title="name" />

    <div class="mx-auto flex max-w-4xl flex-col gap-8 p-4 md:p-6">
        <!-- Back link -->
        <Link href="/events" class="flex w-fit items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft :size="14" />
            Back to events
        </Link>

        <!-- Hero images -->
        <div v-if="event.images.length" class="flex flex-col gap-2">
            <div class="relative aspect-video overflow-hidden rounded-2xl bg-muted">
                <img
                    :src="event.images[activeImage]"
                    :alt="name"
                    class="h-full w-full object-cover transition-opacity duration-300"
                />
                <!-- Status badge -->
                <div class="absolute left-4 top-4">
                    <Badge :variant="statusVariant(event.status)" class="capitalize shadow">
                        {{ event.status.replace('_', ' ') }}
                    </Badge>
                </div>
                <!-- Type chip -->
                <div class="absolute bottom-4 right-4">
                    <span class="rounded-full bg-black/60 px-3 py-1 text-sm capitalize text-white backdrop-blur-sm">
                        {{ event.type }}
                    </span>
                </div>
            </div>

            <!-- Image thumbnails -->
            <div v-if="event.images.length > 1" class="flex gap-2">
                <button
                    v-for="(img, i) in event.images"
                    :key="i"
                    class="h-16 w-16 overflow-hidden rounded-lg border-2 transition-all duration-200"
                    :class="i === activeImage ? 'border-primary' : 'border-transparent opacity-60 hover:opacity-100'"
                    @click="activeImage = i"
                >
                    <img :src="img" :alt="`Image ${i + 1}`" class="h-full w-full object-cover" />
                </button>
            </div>
        </div>

        <!-- Main layout: details + sidebar -->
        <div class="grid gap-8 lg:grid-cols-3">
            <!-- Left: event details -->
            <div class="flex flex-col gap-6 lg:col-span-2">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">{{ name }}</h1>

                    <!-- Meta row -->
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                        <span v-if="startsAt" class="flex items-center gap-1.5">
                            <CalendarDays :size="14" class="text-primary" />
                            <time :datetime="toISOString(startsAt)">
                                {{ formatEventRange(startsAt, endsAt, event.timezone) }}
                            </time>
                        </span>

                        <span v-if="event.location_label" class="flex items-center gap-1.5">
                            <MapPin :size="14" class="text-primary" />
                            {{ event.location_label }}
                            <span v-if="venue" class="text-muted-foreground/60">· {{ venue }}</span>
                        </span>

                        <span class="flex items-center gap-1.5">
                            <Ticket :size="14" class="text-primary" />
                            {{ price }}
                        </span>
                    </div>
                </div>

                <!-- Description -->
                <p v-if="description" class="leading-relaxed text-muted-foreground">
                    {{ description }}
                </p>

                <!-- Tags -->
                <div v-if="tags.length" class="flex flex-wrap gap-2">
                    <span
                        v-for="tag in tags"
                        :key="tag"
                        class="flex items-center gap-1 rounded-full border bg-muted/50 px-3 py-1 text-xs text-muted-foreground"
                    >
                        <Tag :size="10" />
                        {{ tag }}
                    </span>
                </div>
            </div>

            <!-- Right sidebar: registration -->
            <div class="lg:col-span-1">
                <RegisterInterest :event-id="event.id" :attendee-count="attendee_count" :event-status="event.status" />
            </div>
        </div>
    </div>
</template>
