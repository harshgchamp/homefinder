<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface DashboardStats {
    published: number;
    drafts: number;
    scheduled: number;
    agencies: number;
    enquiries_last_7_days: number;
}

const props = defineProps<{
    stats: DashboardStats;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: '/admin' }],
    },
});

const cards = computed(() => [
    { label: 'Published properties', value: props.stats.published },
    { label: 'Drafts', value: props.stats.drafts },
    { label: 'Scheduled', value: props.stats.scheduled },
    { label: 'Agencies', value: props.stats.agencies },
    { label: 'Enquiries (last 7 days)', value: props.stats.enquiries_last_7_days },
]);
</script>

<template>
    <Head title="Admin dashboard" />

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
        <div v-for="card in cards" :key="card.label" class="rounded-xl border p-4">
            <p class="text-sm text-muted-foreground">{{ card.label }}</p>
            <p class="mt-2 text-3xl font-semibold tabular-nums">{{ card.value }}</p>
        </div>
    </div>
</template>