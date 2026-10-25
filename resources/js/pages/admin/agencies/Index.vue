<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { route } from 'ziggy-js';
import Pagination from '@/components/admin/Pagination.vue';
import SelectField from '@/components/admin/SelectField.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useFilters } from '@/composables/useFilters';
import { fieldClass } from '@/lib/fieldClass';
import type { Agency, AgencyFilters, Option, Paginated } from '@/types';

const props = defineProps<{
    agencies: Paginated<Agency>;
    filters: AgencyFilters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Agencies', href: '/admin/agencies' },
        ],
    },
});

// Why only: a filter change reloads just the list and the filters, nothing else.
const RELOAD_ONLY = ['agencies', 'filters'];

// Why the shared composable: debounce, clean query string, partial reload and
// reset work exactly like the properties list.
const { filters, activeCount, reset } = useFilters<AgencyFilters>({
    initial: props.filters,
    defaults: { search: null, status: null, sort: 'name' },
    url: () => route('admin.agencies.index'),
    only: RELOAD_ONLY,
});

// Why typed options: the values must match AgencyIndexRequest's Rule::in() lists.
const statusOptions: Option<'active' | 'inactive'>[] = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
];

const sortOptions: Option<AgencyFilters['sort']>[] = [
    { value: 'name', label: 'Name (A-Z)' },
    { value: 'newest', label: 'Newest first' },
    { value: 'most_properties', label: 'Most properties' },
];

// --- Delete ---------------------------------------------------------------------

// Why one ref drives the dialog: null = closed, an agency = open for that agency.
const agencyToDelete = ref<Agency | null>(null);
const deleting = ref(false);

const destroy = (): void => {
    if (!agencyToDelete.value) {
        return;
    }

    router.delete(route('admin.agencies.destroy', agencyToDelete.value.id), {
        preserveScroll: true,
        onStart: () => (deleting.value = true),
        // Why onFinish: close the dialog whether it worked or the server refused
        // (the toast explains which).
        onFinish: () => {
            deleting.value = false;
            agencyToDelete.value = null;
        },
    });
};
</script>

<template>
    <Head title="Agencies" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Agencies"
            :description="`${agencies.meta.total} ${agencies.meta.total === 1 ? 'agency' : 'agencies'}`"
        />

        <Button as-child>
            <Link :href="route('admin.agencies.create')">Add agency</Link>
        </Button>
    </div>

    <section
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        aria-label="Filters"
    >
        <!-- Why native: filters.search can be null, which <Input> does not accept -->
        <input
            v-model="filters.search"
            type="search"
            placeholder="Search name or email"
            aria-label="Search"
            :class="fieldClass"
        />
        <SelectField
            v-model="filters.status"
            :options="statusOptions"
            placeholder="Any status"
            aria-label="Status"
        />
        <!-- No placeholder: a sort must always be chosen -->
        <SelectField
            v-model="filters.sort"
            :options="sortOptions"
            aria-label="Sort"
        />
        <Button variant="outline" :disabled="activeCount === 0" @click="reset">
            Reset filters{{ activeCount ? ` (${activeCount})` : '' }}
        </Button>
    </section>

    <div class="overflow-x-auto rounded-xl border">
        <table class="w-full text-sm">
            <thead class="border-b bg-muted/50 text-left">
                <tr>
                    <th class="px-4 py-2 font-medium">Name</th>
                    <th class="px-4 py-2 font-medium">Contact</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Properties</th>
                    <th class="px-4 py-2 font-medium">Added</th>
                    <th class="px-4 py-2">
                        <span class="sr-only">Actions</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="agency in agencies.data"
                    :key="agency.id"
                    class="border-b last:border-0"
                >
                    <td class="px-4 py-2 font-medium">{{ agency.name }}</td>
                    <td class="px-4 py-2">
                        <div>{{ agency.email }}</div>
                        <div class="text-muted-foreground">
                            {{ agency.phone }}
                        </div>
                    </td>
                    <td class="px-4 py-2">
                        <Badge
                            :variant="
                                agency.is_active ? 'default' : 'secondary'
                            "
                        >
                            {{ agency.is_active ? 'Active' : 'Inactive' }}
                        </Badge>
                    </td>
                    <td class="px-4 py-2 tabular-nums">
                        {{ agency.properties_count ?? 0 }}
                    </td>
                    <td class="px-4 py-2" :title="agency.created_at ?? ''">
                        {{ agency.created_ago }}
                    </td>
                    <td class="px-4 py-2 text-right whitespace-nowrap">
                        <Button variant="ghost" size="sm" as-child>
                            <Link
                                :href="route('admin.agencies.edit', agency.id)"
                                >Edit</Link
                            >
                        </Button>
                        <!-- Why disabled: the server refuses to delete an agency with
                             properties; the title explains why. The server still checks
                             (trashed properties are not in this count). -->
                        <Button
                            variant="ghost"
                            size="sm"
                            class="text-destructive"
                            :disabled="(agency.properties_count ?? 0) > 0"
                            :title="
                                (agency.properties_count ?? 0) > 0
                                    ? 'Has properties: mark it inactive instead'
                                    : undefined
                            "
                            @click="agencyToDelete = agency"
                        >
                            Delete
                        </Button>
                    </td>
                </tr>
                <!-- Why: an empty table with no explanation looks broken -->
                <tr v-if="agencies.data.length === 0">
                    <td
                        colspan="6"
                        class="px-4 py-8 text-center text-muted-foreground"
                    >
                        {{
                            activeCount
                                ? 'No agencies match these filters.'
                                : 'No agencies yet.'
                        }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <Pagination :meta="agencies.meta" :only="RELOAD_ONLY" />

    <!-- Why a dialog, not confirm(): accessible (focus trap, ESC, screen readers)
         and it can show which agency is being deleted. -->
    <Dialog
        :open="agencyToDelete !== null"
        @update:open="(open) => !open && (agencyToDelete = null)"
    >
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Delete agency?</DialogTitle>
                <DialogDescription>
                    "{{ agencyToDelete?.name }}" will be deleted permanently.
                    Its agents stay as normal users. This cannot be undone.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter>
                <DialogClose as-child>
                    <Button variant="outline">Cancel</Button>
                </DialogClose>
                <Button
                    variant="destructive"
                    :disabled="deleting"
                    @click="destroy"
                >
                    Delete
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
