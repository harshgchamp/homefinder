<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import Pagination from '@/components/admin/Pagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Paginated, PropertyType } from '@/types';

const props = defineProps<{
    types: Paginated<PropertyType>;
    filters: { search: string };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Property types', href: '/admin/property-types' },
        ],
    },
});

// Why: start from the server's value so the box still shows the term after a reload.
const search = ref(props.filters.search);

// Why: wait 300ms after typing stops, so there is one request per search, not one per key.
watchDebounced(
    search,
    (value) => {
        router.get(
            route('admin.property-types.index'),
            value ? { search: value } : {},
            { preserveState: true, replace: true },
        );
    },
    { debounce: 300 },
);

// Why confirm: deleting cannot be undone. A type still in use is refused by
// the server with an error toast, so the button can stay simple.
const destroy = (type: PropertyType): void => {
    if (confirm(`Delete "${type.name}"?`)) {
        router.delete(route('admin.property-types.destroy', type.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <Head title="Property types" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Property types"
            :description="`${types.meta.total} in total`"
        />

        <Button as-child>
            <Link :href="route('admin.property-types.create')"
                >Add property type</Link
            >
        </Button>
    </div>

    <Input
        v-model="search"
        type="search"
        placeholder="Search property types"
        class="max-w-sm"
    />

    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="border-b bg-muted/50 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="px-4 py-2 font-medium">Slug</th>
                    <th class="px-4 py-2 font-medium">Properties</th>
                    <th class="px-4 py-2 font-medium">Created</th>
                    <th class="px-4 py-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="type in types.data"
                    :key="type.id"
                    class="border-b last:border-0"
                >
                    <td class="px-4 py-2">{{ type.name }}</td>
                    <td class="px-4 py-2 text-muted-foreground">
                        {{ type.slug }}
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ type.properties_count ?? 0 }}
                    </td>
                    <td class="px-4 py-2" :title="type.created_at ?? ''">
                        {{ type.created_ago }}
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <!-- Edit opens the SAME form as "Add", filled in -->
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="
                                    route('admin.property-types.edit', type.id)
                                "
                                >Edit</Link
                            >
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            @click="destroy(type)"
                        >
                            Delete
                        </Button>
                    </td>
                </tr>
                <!-- Why: an empty table with no explanation looks broken -->
                <tr v-if="types.data.length === 0">
                    <td
                        colspan="5"
                        class="px-4 py-8 text-center text-muted-foreground"
                    >
                        {{
                            filters.search
                                ? `No property types match "${filters.search}".`
                                : 'No property types yet.'
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="types.meta" />
</template>
