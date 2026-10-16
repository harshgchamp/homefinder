<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PropertyForm from '@/components/admin/PropertyForm.vue';
import Heading from '@/components/Heading.vue';
import type { PropertyFormOptions } from '@/types';

defineProps<{
    options: PropertyFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Properties', href: '/admin/properties' },
            { title: 'Add', href: '/admin/properties/create' },
        ],
    },
});
</script>

<template>
    <Head title="Add property" />

    <Heading
        title="Add property"
        description="The web address (slug) is generated from the title."
    />

    <!-- Why a shared component: Create and Edit have the same fields;
         only the submit URL and the starting values differ. -->
    <PropertyForm :options="options" />
</template>
