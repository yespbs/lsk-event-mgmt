<script setup lang="ts">
import type { EventFilters } from '@/types';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    statuses: string[];
    cities: string[];
}>();

const filters = defineModel<EventFilters>({ required: true });

const emit = defineEmits<{ apply: [] }>();
</script>

<template>
    <form class="flex flex-wrap items-end gap-3" @submit.prevent="emit('apply')">
        <!-- Status -->
        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-status">Status</label>
            <select
                id="filter-status"
                v-model="filters.status"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="">All statuses</option>
                <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
            </select>
        </div>

        <!-- Date from -->
        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-date-from">From</label>
            <input
                id="filter-date-from"
                v-model="filters.date_from"
                type="date"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
        </div>

        <!-- Date to -->
        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-date-to">To</label>
            <input
                id="filter-date-to"
                v-model="filters.date_to"
                type="date"
                class="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            />
        </div>

        <!-- Location -->
        <div class="flex flex-col gap-1">
            <label class="text-xs text-muted-foreground" for="filter-location">Location</label>
            <select
                id="filter-location"
                v-model="filters.location"
                class="h-9 min-w-48 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
            >
                <option value="">All locations</option>
                <option v-for="city in cities" :key="city" :value="city">{{ city }}</option>
            </select>
        </div>

        <Button type="submit">Filter</Button>

        <!-- Clear -->
        <Button
            v-if="filters.status || filters.date_from || filters.date_to || filters.location"
            type="button"
            variant="ghost"
            @click="
                filters.status = '';
                filters.date_from = '';
                filters.date_to = '';
                filters.location = '';
                emit('apply');
            "
        >
            Clear
        </Button>
    </form>
</template>
