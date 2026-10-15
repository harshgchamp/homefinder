<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import PropertyController from '@/actions/App/Http/Controllers/Admin/PropertyController';
import PropertyExportController from '@/actions/App/Http/Controllers/Admin/PropertyExportController';
import Pagination from '@/components/admin/Pagination.vue';
import SelectField from '@/components/admin/SelectField.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/composables/useFilters';
import { fieldClass } from '@/lib/fieldClass';
import type {
    Paginated,
    Property,
    PropertyFilterOptions,
    PropertyFilters,
} from '@/types';

const props = defineProps<{
    properties: Paginated<Property>;
    filters: PropertyFilters;
    options: PropertyFilterOptions;
    sorts: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Properties', href: '/admin/properties' },
        ],
    },
});

// --- Filters ---------------------------------------------------------------

// Why only: on filter changes, reload just the list and the filters. The
// server skips building the options (agencies, cities...) because that prop is a closure.
const RELOAD_ONLY = ['properties', 'filters'];

const { filters, query, activeCount, reset } = useFilters<PropertyFilters>({
    initial: props.filters,
    defaults: {
        search: null,
        listing_type: null,
        status: null,
        publication: null,
        agency_id: null,
        property_type_id: null,
        city: null,
        min_price: null,
        max_price: null,
        min_bedrooms: null,
        features: [],
        trashed: null,
        sort: 'newest',
    },
    url: () => PropertyController.index.url(),
    only: RELOAD_ONLY,
});

const cityOptions = computed(() =>
    props.options.cities.map((city) => ({ value: city, label: city })),
);

const bedroomOptions = [1, 2, 3, 4, 5].map((n) => ({
    value: n,
    label: `${n}+ beds`,
}));

const sortLabels: Record<string, string> = {
    newest: 'Newest first',
    oldest: 'Oldest first',
    price_asc: 'Price: low to high',
    price_desc: 'Price: high to low',
    title: 'Title A–Z',
};
const sortOptions = computed(() =>
    props.sorts.map((value) => ({
        value,
        label: sortLabels[value] ?? value,
    })),
);

const trashedOptions = [
    { value: 'with', label: 'Include trashed' },
    { value: 'only', label: 'Only trashed' },
];

// Why: the export link carries the same query string as the list, so the CSV
// contains exactly the rows the admin is looking at (on every page, not just this one).
const exportUrl = computed(() =>
    PropertyExportController.url({ query: query.value }),
);

// --- Bulk selection --------------------------------------------------------

const selected = ref<number[]>([]);

// Why a writable computed: the header checkbox both shows the state
// ("are all rows on this page ticked?") and changes it when clicked.
const allSelected = computed({
    get: () =>
        props.properties.data.length > 0 &&
        props.properties.data.every((p) => selected.value.includes(p.id)),
    set: (value: boolean) => {
        selected.value = value ? props.properties.data.map((p) => p.id) : [];
    },
});

const bulk = (action: 'publish' | 'unpublish' | 'delete'): void => {
    if (
        action === 'delete' &&
        !confirm(`Move ${selected.value.length} properties to the trash?`)
    ) {
        return;
    }

    router.post(
        PropertyController.bulk.url(),
        { action, ids: selected.value },
        {
            preserveScroll: true,
            onSuccess: () => (selected.value = []),
        },
    );
};

// --- Row actions -----------------------------------------------------------

const destroy = (property: Property): void => {
    if (confirm(`Move "${property.title}" to the trash?`)) {
        router.delete(PropertyController.destroy.url(property.id), {
            preserveScroll: true,
        });
    }
};

const restore = (property: Property): void => {
    router.patch(
        PropertyController.restore.url(property.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const forceDelete = (property: Property): void => {
    if (
        confirm(
            `Permanently delete "${property.title}"? This cannot be undone.`,
        )
    ) {
        router.delete(PropertyController.forceDelete.url(property.id), {
            preserveScroll: true,
        });
    }
};

// Why: badge colour per publication state, so drafts stand out in the list.
const publicationVariant = (
    value: string,
): 'default' | 'secondary' | 'outline' =>
    value === 'published'
        ? 'default'
        : value === 'scheduled'
          ? 'secondary'
          : 'outline';

// Why: a broken or missing image (e.g. seeded demo paths) shows the grey box instead.
const hideBrokenImage = (event: Event): void => {
    (event.target as HTMLImageElement).style.visibility = 'hidden';
};
</script>

<template>
    <Head title="Properties" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Properties"
            :description="`${properties.meta.total} matching ${properties.meta.total === 1 ? 'property' : 'properties'}`"
        />

        <div class="flex gap-2">
            <!-- Why <a>, not <Link>: a file download must be a normal browser request -->
            <Button variant="outline" as-child>
                <a :href="exportUrl">Export CSV</a>
            </Button>
            <Button as-child>
                <Link :href="PropertyController.create()">Add property</Link>
            </Button>
        </div>
    </div>

    <!-- Filter bar. Every v-model writes into `filters`; useFilters() reloads the list. -->
    <section
        class="grid gap-3 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-4"
        aria-label="Filters"
    >
        <!-- Why native: filters.search can be null, which <Input> does not accept -->
        <input
            v-model="filters.search"
            type="search"
            placeholder="Search title, address, postcode"
            aria-label="Search"
            :class="[fieldClass, 'sm:col-span-2']"
        />
        <SelectField
            v-model="filters.listing_type"
            :options="options.listingTypes"
            placeholder="Sale or rent"
        />
        <SelectField
            v-model="filters.status"
            :options="options.statuses"
            placeholder="Any status"
        />
        <SelectField
            v-model="filters.publication"
            :options="options.publications"
            placeholder="Any publication"
        />
        <SelectField
            v-model="filters.agency_id"
            :options="options.agencies"
            placeholder="Any agency"
        />
        <SelectField
            v-model="filters.property_type_id"
            :options="options.types"
            placeholder="Any type"
        />
        <SelectField
            v-model="filters.city"
            :options="cityOptions"
            placeholder="Any city"
        />
        <div class="flex gap-2">
            <!-- Why .number + :class: v-model.number gives a number (or '' when cleared) -->
            <input
                v-model.number="filters.min_price"
                type="number"
                min="0"
                step="1000"
                placeholder="Min £"
                aria-label="Minimum price"
                :class="fieldClass"
            />
            <input
                v-model.number="filters.max_price"
                type="number"
                min="0"
                step="1000"
                placeholder="Max £"
                aria-label="Maximum price"
                :class="fieldClass"
            />
        </div>
        <SelectField
            v-model="filters.min_bedrooms"
            :options="bedroomOptions"
            placeholder="Any bedrooms"
        />
        <SelectField
            v-model="filters.trashed"
            :options="trashedOptions"
            placeholder="Hide trashed"
        />
        <SelectField v-model="filters.sort" :options="sortOptions" />

        <!-- Why <details>: a simple native dropdown for a long checkbox list -->
        <details class="relative sm:col-span-2 lg:col-span-4">
            <summary class="cursor-pointer text-sm text-muted-foreground">
                Features
                <span v-if="filters.features.length">
                    ({{ filters.features.length }} selected, must have all)
                </span>
            </summary>
            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                <label
                    v-for="feature in options.features"
                    :key="feature.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <!-- Why v-model on an array: Vue adds/removes the value when ticked -->
                    <input
                        v-model="filters.features"
                        type="checkbox"
                        :value="feature.value"
                    />
                    {{ feature.label }}
                </label>
            </div>
        </details>

        <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-4">
            <Button
                variant="ghost"
                size="sm"
                :disabled="activeCount === 0"
                @click="reset"
            >
                Reset filters{{ activeCount ? ` (${activeCount})` : '' }}
            </Button>
        </div>
    </section>

    <!-- Bulk actions appear only when something is ticked -->
    <div
        v-if="selected.length"
        class="flex flex-wrap items-center gap-2 rounded-xl border bg-muted/50 px-4 py-2 text-sm"
    >
        <span class="font-medium">{{ selected.length }} selected</span>
        <Button size="sm" variant="outline" @click="bulk('publish')"
            >Publish</Button
        >
        <Button size="sm" variant="outline" @click="bulk('unpublish')"
            >Unpublish</Button
        >
        <Button
            size="sm"
            variant="outline"
            class="text-destructive"
            @click="bulk('delete')"
            >Move to trash</Button
        >
        <Button size="sm" variant="ghost" @click="selected = []">Clear</Button>
    </div>

    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="border-b bg-muted/50 text-left">
                <tr>
                    <th class="w-10 px-4 py-2">
                        <input
                            v-model="allSelected"
                            type="checkbox"
                            aria-label="Select all on this page"
                        />
                    </th>
                    <th class="px-4 py-2 font-medium">Property</th>
                    <th class="px-4 py-2 font-medium">Type</th>
                    <th class="px-4 py-2 font-medium">Price</th>
                    <th class="px-4 py-2 font-medium">Beds / baths</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Enquiries</th>
                    <th class="px-4 py-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="property in properties.data"
                    :key="property.id"
                    class="border-b last:border-0"
                    :class="{ 'opacity-60': property.is_trashed }"
                >
                    <td class="px-4 py-2">
                        <input
                            v-model="selected"
                            type="checkbox"
                            :value="property.id"
                            :aria-label="`Select ${property.title}`"
                        />
                    </td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-3">
                            <div
                                class="size-12 shrink-0 overflow-hidden rounded-md bg-muted"
                            >
                                <img
                                    v-if="property.main_image_url"
                                    :src="property.main_image_url"
                                    alt=""
                                    class="size-full object-cover"
                                    loading="lazy"
                                    @error="hideBrokenImage"
                                />
                            </div>
                            <div class="min-w-0">
                                <Link
                                    :href="PropertyController.show(property.id)"
                                    class="font-medium hover:underline"
                                >
                                    {{ property.title }}
                                </Link>
                                <p class="text-muted-foreground">
                                    {{ property.city }},
                                    {{ property.postcode }} ·
                                    {{ property.agency?.name }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        {{ property.property_type?.name }}
                        <p class="text-muted-foreground">
                            {{ property.listing_type.label }}
                        </p>
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ property.formatted_price }}
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ property.bedrooms }} / {{ property.bathrooms }}
                    </td>
                    <td class="space-y-1 px-4 py-2">
                        <Badge
                            :variant="
                                publicationVariant(property.publication.value)
                            "
                        >
                            {{ property.publication.label }}
                        </Badge>
                        <p class="text-muted-foreground">
                            {{ property.status.label }}
                        </p>
                        <Badge v-if="property.is_trashed" variant="destructive"
                            >Trashed</Badge
                        >
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ property.enquiries_count ?? 0 }}
                        <span class="text-muted-foreground">
                            · ♥ {{ property.favourites_count ?? 0 }}
                        </span>
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <template v-if="property.is_trashed">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="restore(property)"
                                >Restore</Button
                            >
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="forceDelete(property)"
                                >Delete forever</Button
                            >
                        </template>
                        <template v-else>
                            <Button variant="ghost" size="sm" as-child>
                                <Link
                                    :href="PropertyController.edit(property.id)"
                                    >Edit</Link
                                >
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                @click="destroy(property)"
                                >Delete</Button
                            >
                        </template>
                    </td>
                </tr>

                <tr v-if="properties.data.length === 0">
                    <td
                        colspan="8"
                        class="px-4 py-8 text-center text-muted-foreground"
                    >
                        {{
                            activeCount
                                ? 'No properties match these filters.'
                                : 'No properties yet.'
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="properties.meta" :only="RELOAD_ONLY" />
</template>
