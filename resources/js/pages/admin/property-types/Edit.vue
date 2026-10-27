<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PropertyTypeForm from '@/components/admin/PropertyTypeForm.vue';
import Heading from '@/components/Heading.vue';
import type { PropertyType } from '@/types';

// Why: a single plain object, because the controller called resolve()
// on the resource instead of sending the { data: ... } wrapper.
const props = defineProps<{
    type: PropertyType;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Property types', href: '/admin/property-types' },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${props.type.name}`" />

    <Heading
        title="Edit property type"
        :description="`Slug: ${props.type.slug}. Last updated ${props.type.updated_at}.`"
    />

    <!-- :type given -> the form is in EDIT mode (filled in, PUT to update) -->
    <PropertyTypeForm :type="props.type" />
</template>
