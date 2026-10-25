<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgencyForm from '@/components/admin/AgencyForm.vue';
import Heading from '@/components/Heading.vue';
import type { Agency } from '@/types';

// Why: a single plain object here, because the controller called resolve()
// on the resource instead of sending the { data: ... } wrapper.
const props = defineProps<{
    agency: Agency;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Agencies', href: '/admin/agencies' },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${props.agency.name}`" />

    <Heading
        title="Edit agency"
        :description="`Slug: ${props.agency.slug} · ${props.agency.properties_count ?? 0} properties · added ${props.agency.created_at}`"
    />

    <AgencyForm :agency="props.agency" />
</template>
