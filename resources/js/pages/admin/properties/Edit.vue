<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PropertyForm from '@/components/admin/PropertyForm.vue';
import Heading from '@/components/Heading.vue';
import type { Property, PropertyFormOptions } from '@/types';

const props = defineProps<{
    property: Property;
    options: PropertyFormOptions;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Properties', href: '/admin/properties' },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${props.property.title}`" />

    <Heading
        title="Edit property"
        :description="`Slug: ${props.property.slug} (does not change when the title changes). Updated ${props.property.updated_ago}.`"
    />

    <!-- Why :key: if Inertia reuses this page for another property, a new key
         creates a fresh form instead of keeping the old property's values. -->
    <PropertyForm
        :key="props.property.id"
        :property="props.property"
        :options="options"
    />
</template>
