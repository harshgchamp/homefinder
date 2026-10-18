<script setup lang="ts">
import { Deferred, Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Property } from '@/types';

type RecentEnquiry = {
    id: number;
    message: string;
    user: string;
    email: string;
    created_ago: string | null;
};

const props = defineProps<{
    property: Property;
    recentEnquiries: RecentEnquiry[];
    // Why optional: a deferred prop is missing on the first render and
    // arrives in a second request a moment later.
    enquiriesPerMonth?: { month: string; count: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Properties', href: '/admin/properties' },
            { title: 'Details', href: '#' },
        ],
    },
});

// Why: label/value pairs rendered with one v-for instead of repeating markup.
const details = computed(() => [
    { label: 'Agency', value: props.property.agency?.name },
    { label: 'Type', value: props.property.property_type?.name },
    { label: 'For', value: props.property.listing_type.label },
    { label: 'Status', value: props.property.status.label },
    { label: 'Price', value: props.property.formatted_price },
    {
        label: 'Bedrooms / bathrooms',
        value: `${props.property.bedrooms} / ${props.property.bathrooms}`,
    },
    {
        label: 'Furnished',
        value:
            props.property.furnished === null ||
            props.property.furnished === undefined
                ? 'Not specified'
                : props.property.furnished
                  ? 'Yes'
                  : 'No',
    },
    {
        label: 'Address',
        value: `${props.property.address_line_1}, ${props.property.city} ${props.property.postcode}`,
    },
    // Filled in by the GeocodeProperty job (postcode -> coordinates)
    {
        label: 'Map position',
        value: props.property.location
            ? `${props.property.location.latitude}, ${props.property.location.longitude}`
            : 'Not found yet',
    },
    { label: 'Slug', value: props.property.slug },
    { label: 'Published', value: props.property.published_at ?? '—' },
    // Set by the RecordLastEnquiryOnProperty listener
    {
        label: 'Last enquiry',
        value: props.property.last_enquiry_ago ?? 'Never',
    },
    { label: 'Created', value: props.property.created_at },
    { label: 'Saved by', value: `${props.property.favourites_count} users` },
]);

// Why: bar widths are a share of the busiest month, so the longest bar fills the row.
const maxPerMonth = computed(() =>
    Math.max(1, ...(props.enquiriesPerMonth ?? []).map((m) => m.count)),
);

const destroy = (): void => {
    if (confirm(`Move "${props.property.title}" to the trash?`)) {
        router.delete(route('admin.properties.destroy', props.property.id));
    }
};

const restore = (): void => {
    router.patch(route('admin.properties.restore', props.property.id));
};

const forceDelete = (): void => {
    if (confirm('Permanently delete this property? This cannot be undone.')) {
        router.delete(
            route('admin.properties.force-delete', props.property.id),
        );
    }
};

const hideBrokenImage = (event: Event): void => {
    (event.target as HTMLImageElement).style.visibility = 'hidden';
};
</script>

<template>
    <Head :title="property.title" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <Heading
                :title="property.title"
                :description="`Updated ${property.updated_ago}`"
            />
            <div class="-mt-6 flex flex-wrap gap-2">
                <Badge>{{ property.publication.label }}</Badge>
                <Badge variant="secondary">{{ property.status.label }}</Badge>
                <Badge v-if="property.is_trashed" variant="destructive">
                    In trash since {{ property.deleted_at }}
                </Badge>
            </div>
        </div>

        <div class="flex gap-2">
            <template v-if="property.is_trashed">
                <Button
                    v-if="property.can?.restore"
                    variant="outline"
                    @click="restore"
                    >Restore</Button
                >
                <Button
                    v-if="property.can?.forceDelete"
                    variant="destructive"
                    @click="forceDelete"
                    >Delete forever</Button
                >
            </template>
            <template v-else>
                <!-- Only published listings have a public page (drafts give 404) -->
                <Button
                    v-if="property.publication.value === 'published'"
                    variant="outline"
                    as-child
                >
                    <a
                        :href="route('properties.show', property.slug)"
                        target="_blank"
                        rel="noopener"
                        >View public page</a
                    >
                </Button>
                <Button v-if="property.can?.update" as-child>
                    <Link :href="route('admin.properties.edit', property.id)"
                        >Edit</Link
                    >
                </Button>
                <Button
                    v-if="property.can?.delete"
                    variant="outline"
                    class="text-destructive"
                    @click="destroy"
                    >Delete</Button
                >
            </template>
        </div>
    </div>

    <div
        v-if="property.images?.length"
        class="grid grid-cols-2 gap-3 sm:grid-cols-4"
    >
        <div
            v-for="image in property.images"
            :key="image.id"
            class="aspect-[4/3] overflow-hidden rounded-lg bg-muted"
        >
            <img
                :src="image.url"
                alt=""
                class="size-full object-cover"
                loading="lazy"
                @error="hideBrokenImage"
            />
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="space-y-6 lg:col-span-2">
            <dl
                class="grid gap-x-6 gap-y-3 rounded-xl border p-4 sm:grid-cols-2"
            >
                <div v-for="item in details" :key="item.label">
                    <dt class="text-sm text-muted-foreground">
                        {{ item.label }}
                    </dt>
                    <dd>{{ item.value }}</dd>
                </div>
            </dl>

            <div class="space-y-2">
                <h3 class="font-medium">Description</h3>
                <!-- whitespace-pre-line keeps the line breaks the agent typed -->
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ property.description }}
                </p>
            </div>

            <div v-if="property.features?.length" class="space-y-2">
                <h3 class="font-medium">Features</h3>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="feature in property.features"
                        :key="feature.id"
                        variant="outline"
                        >{{ feature.name }}</Badge
                    >
                </div>
            </div>
        </section>

        <aside class="space-y-6">
            <section class="space-y-3 rounded-xl border p-4">
                <h3 class="font-medium">
                    Enquiries per month
                    <span class="font-normal text-muted-foreground">
                        ({{ property.enquiries_count }} in total)
                    </span>
                </h3>

                <!-- Deferred: shows the fallback until the enquiriesPerMonth prop arrives -->
                <Deferred data="enquiriesPerMonth">
                    <template #fallback>
                        <p class="text-sm text-muted-foreground">Loading…</p>
                    </template>

                    <ul class="space-y-2">
                        <li
                            v-for="row in enquiriesPerMonth"
                            :key="row.month"
                            class="grid grid-cols-[4.5rem_1fr_2rem] items-center gap-2 text-sm"
                            :title="`${row.month}: ${row.count} ${row.count === 1 ? 'enquiry' : 'enquiries'}`"
                        >
                            <span class="text-muted-foreground">{{
                                row.month
                            }}</span>
                            <span class="h-2 rounded-full bg-muted">
                                <span
                                    class="block h-full rounded-full bg-primary"
                                    :style="{
                                        width: `${(row.count / maxPerMonth) * 100}%`,
                                    }"
                                />
                            </span>
                            <span class="text-right tabular-nums">{{
                                row.count
                            }}</span>
                        </li>
                    </ul>
                </Deferred>
            </section>

            <section class="space-y-3 rounded-xl border p-4">
                <h3 class="font-medium">Recent enquiries</h3>
                <p
                    v-if="recentEnquiries.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No enquiries yet.
                </p>
                <ul v-else class="space-y-3">
                    <li
                        v-for="enquiry in recentEnquiries"
                        :key="enquiry.id"
                        class="text-sm"
                    >
                        <p class="font-medium">
                            {{ enquiry.user }}
                            <span class="font-normal text-muted-foreground"
                                >· {{ enquiry.created_ago }}</span
                            >
                        </p>
                        <p class="text-muted-foreground">
                            {{ enquiry.message }}
                        </p>
                    </li>
                </ul>
            </section>
        </aside>
    </div>
</template>
