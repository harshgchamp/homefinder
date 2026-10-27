<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PropertyTypeForm from '@/components/admin/PropertyTypeForm.vue';
import Heading from '@/components/Heading.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Property types', href: '/admin/property-types' },
            { title: 'Add', href: '/admin/property-types/create' },
        ],
    },
});
</script>

<template>
    <Head title="Add property type" />

    <Heading
        title="Add property type"
        description="The slug is generated from the name."
    />

    <!-- No :type prop -> the form is in CREATE mode (POST to store) -->
    <PropertyTypeForm />
</template>
