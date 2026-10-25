<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AgencyForm from '@/components/admin/AgencyForm.vue';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Agencies', href: '/admin/agencies' },
            { title: 'Add', href: '/admin/agencies/create' },
        ],
    },
});
</script>

<template>
    <Head title="Add agency" />

    <Heading
        title="Add agency"
        description="The web address (slug) is generated from the name."
    />

    <!-- Why a shared component: Create and Edit have the same fields;
         only the submit URL and the starting values differ. -->
    <AgencyForm />
</template>
