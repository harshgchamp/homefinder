<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AgencyForm from '@/components/admin/AgencyForm.vue';
import NotesPanel from '@/components/admin/NotesPanel.vue';
import Heading from '@/components/Heading.vue';
import type { Agency, Note } from '@/types';

// Why: a single plain object here, because the controller called resolve()
// on the resource instead of sending the { data: ... } wrapper.
const props = defineProps<{
    agency: Agency;
    notes: Note[];
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

    <!-- Why a grid: the form keeps its narrow width, notes sit beside it on wide screens -->
    <div class="grid gap-6 lg:grid-cols-2">
        <AgencyForm :agency="props.agency" />

        <!-- Same component as the property page: one notes table, one panel -->
        <NotesPanel
            :notes="props.notes"
            :store-url="route('admin.agencies.notes.store', props.agency.id)"
        />
    </div>
</template>
