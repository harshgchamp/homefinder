<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';
import FeatureController from '@/actions/App/Http/Controllers/Admin/FeatureController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Feature, Paginated } from '@/types';

// Why: typed props document exactly what the controller sends, one per kind of value:
// a paginated collection, an array, two numbers and a list of strings.
const props = defineProps<{
    features: Paginated<Feature>;
    filters: { search: string };
    total: number;
    addedThisWeek: number;
    recentNames: string[];
}>();

// Why: breadcrumbs are passed to the layout this way in this starter kit;
// the page itself is not wrapped in a layout component.
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Features', href: '/admin/features' },
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
            FeatureController.index.url(),
            value ? { search: value } : {},
            {
                preserveState: true, // keep focus in the search box
                replace: true, // do not fill the browser history with every search
            },
        );
    },
    { debounce: 300 },
);

// Why: deleting cannot be undone, so ask first. preserveScroll keeps the list position.
const destroy = (feature: Feature): void => {
    if (!confirm(`Delete "${feature.name}"?`)) {
        return;
    }

    router.delete(FeatureController.destroy.url(feature.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Features" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <!-- Plain numbers from the controller -->
        <Heading
            title="Features"
            :description="`${total} in total, ${addedThisWeek} added this week`"
        />

        <Button as-child>
            <Link :href="FeatureController.create()">Add feature</Link>
        </Button>
    </div>

    <!-- List of strings from the controller -->
    <p v-if="recentNames.length" class="text-sm text-muted-foreground">
        Recently added: {{ recentNames.join(', ') }}
    </p>

    <Input
        v-model="search"
        type="search"
        placeholder="Search features"
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
                <!-- Why :key: lets Vue reuse the right row when the list changes -->
                <tr
                    v-for="feature in features.data"
                    :key="feature.id"
                    class="border-b last:border-0"
                >
                    <td class="px-4 py-2">
                        {{ feature.name }}
                        <!-- Accessor value (is_new) computed on the server -->
                        <Badge
                            v-if="feature.is_new"
                            variant="secondary"
                            class="ml-2"
                            >New</Badge
                        >
                    </td>
                    <td class="px-4 py-2 text-muted-foreground">
                        {{ feature.slug }}
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ feature.properties_count ?? 0 }}
                    </td>
                    <!-- Dates arrive already formatted; title shows the exact date on hover -->
                    <td class="px-4 py-2" :title="feature.created_at ?? ''">
                        {{ feature.created_ago }}
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <Button variant="ghost" size="sm" as-child>
                            <Link :href="FeatureController.edit(feature.id)"
                                >Edit</Link
                            >
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            @click="destroy(feature)"
                        >
                            Delete
                        </Button>
                    </td>
                </tr>
                <!-- Why: an empty table with no explanation looks broken -->
                <tr v-if="features.data.length === 0">
                    <td
                        colspan="5"
                        class="px-4 py-8 text-center text-muted-foreground"
                    >
                        {{
                            filters.search
                                ? `No features match "${filters.search}".`
                                : 'No features yet.'
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Why: Laravel builds the page links, including ?search=, so Vue only renders them.
         v-html is needed because the labels contain entities such as &laquo;. -->
    <nav
        v-if="features.meta.last_page > 1"
        class="flex flex-wrap gap-1"
        aria-label="Pagination"
    >
        <template v-for="link in features.meta.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                class="rounded-md border px-3 py-1 text-sm"
                :class="{ 'bg-muted font-medium': link.active }"
            >
                <span v-html="link.label" />
            </Link>
            <span
                v-else
                class="rounded-md border px-3 py-1 text-sm text-muted-foreground"
                v-html="link.label"
            />
        </template>
    </nav>
</template>
