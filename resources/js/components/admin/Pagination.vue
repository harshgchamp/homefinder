<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginated } from '@/types';

// Why generic-free: pagination only needs the meta block, whatever the rows are.
defineProps<{
    meta: Paginated<unknown>['meta'];
    /** Partial reload: fetch only these props when changing page. */
    only?: string[];
}>();
</script>

<template>
    <!-- Why: Laravel builds the page links (with the current filters in them),
         so this component only renders them. v-html because the labels
         contain entities such as &laquo;. -->
    <nav
        v-if="meta.last_page > 1"
        class="flex flex-wrap items-center gap-1"
        aria-label="Pagination"
    >
        <template v-for="link in meta.links" :key="link.label">
            <Link
                v-if="link.url"
                :href="link.url"
                :only="only"
                preserve-scroll
                class="rounded-md border px-3 py-1 text-sm hover:bg-muted"
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

        <span class="ml-2 text-sm text-muted-foreground">
            {{ meta.from }}–{{ meta.to }} of {{ meta.total }}
        </span>
    </nav>
</template>
