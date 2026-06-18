<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { CheckCircle, Users } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    eventId: string;
    attendeeCount: number;
}>();

const succeeded = ref(false);

const form = useForm({
    name: '',
    email: '',
});

function submit() {
    form.post(`/events/${props.eventId}/attendees`, {
        onSuccess: () => {
            succeeded.value = true;
            form.reset();
        },
    });
}
</script>

<template>
    <div class="rounded-xl border bg-card p-6 shadow-sm">
        <div class="mb-4 flex items-center gap-2">
            <Users :size="18" class="text-primary" />
            <h2 class="font-semibold">Register Interest</h2>
            <span class="ml-auto text-sm text-muted-foreground">
                {{ attendeeCount }} {{ attendeeCount === 1 ? 'attendee' : 'attendees' }}
            </span>
        </div>

        <!-- Success state -->
        <div
            v-if="succeeded"
            class="flex items-center gap-3 rounded-lg bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950/30 dark:text-green-400"
        >
            <CheckCircle :size="18" class="shrink-0" />
            <span>You're on the list! A confirmation email is on its way.</span>
        </div>

        <!-- Form -->
        <form v-else class="flex flex-col gap-4" @submit.prevent="submit">
            <div class="flex flex-col gap-1.5">
                <Label for="attendee-name">Name</Label>
                <Input
                    id="attendee-name"
                    v-model="form.name"
                    type="text"
                    placeholder="Your name"
                    autocomplete="name"
                    :disabled="form.processing"
                />
                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="flex flex-col gap-1.5">
                <Label for="attendee-email">Email</Label>
                <Input
                    id="attendee-email"
                    v-model="form.email"
                    type="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    :disabled="form.processing"
                />
                <p v-if="form.errors.email" class="text-xs text-destructive">{{ form.errors.email }}</p>
            </div>

            <Button type="submit" :disabled="form.processing" class="w-full">
                <span v-if="form.processing">Registering…</span>
                <span v-else>Register Interest</span>
            </Button>
        </form>
    </div>
</template>
