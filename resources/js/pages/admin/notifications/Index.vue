<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import Pagination from '@/components/admin/Pagination.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

// Why: mirrors NewEnquiryNotification::toArray(), stored as JSON in notifications.data.
type EnquiryData = {
    enquiry_id: number;
    property_id: number;
    property_title: string;
    from: string;
    excerpt: string;
};

type AdminNotification = {
    id: string; // UUID
    type: string; // 'enquiry.received' (databaseType() in the notification)
    data: EnquiryData;
    read: boolean;
    created_ago: string | null;
};

// Why not Paginated<...>['meta']: through() keeps Laravel's plain paginator
// shape ({ data, links, current_page, ... }) because no Resource wraps it.
const props = defineProps<{
    notifications: {
        data: AdminNotification[];
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
        links: Paginated<unknown>['meta']['links'];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Notifications', href: '/admin/notifications' },
        ],
    },
});

const hasUnread = computed(() => props.notifications.data.some((n) => !n.read));

// Why POST, not a plain link: opening it changes data (marks it read), and
// GET requests must never change data (prefetching or crawlers could trigger it).
const open = (notification: AdminNotification): void => {
    router.post(route('admin.notifications.read', notification.id));
};

const markAllRead = (): void => {
    router.post(
        route('admin.notifications.read-all'),
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <Head title="Notifications" />

    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Notifications"
            description="New enquiries about any property."
        />
        <Button variant="outline" :disabled="!hasUnread" @click="markAllRead">
            Mark all as read
        </Button>
    </div>

    <ul class="divide-y rounded-xl border">
        <li v-for="notification in notifications.data" :key="notification.id">
            <button
                type="button"
                class="flex w-full items-start gap-3 px-4 py-3 text-left text-sm hover:bg-muted/50"
                @click="open(notification)"
            >
                <!-- Unread dot -->
                <span
                    class="mt-1.5 size-2 shrink-0 rounded-full"
                    :class="notification.read ? 'bg-transparent' : 'bg-primary'"
                    :aria-label="notification.read ? 'Read' : 'Unread'"
                />
                <span class="min-w-0 flex-1">
                    <span
                        class="block"
                        :class="{ 'font-medium': !notification.read }"
                    >
                        {{ notification.data.from }} enquired about
                        {{ notification.data.property_title }}
                    </span>
                    <span class="block truncate text-muted-foreground">
                        {{ notification.data.excerpt }}
                    </span>
                </span>
                <span class="shrink-0 text-muted-foreground">
                    {{ notification.created_ago }}
                </span>
            </button>
        </li>

        <li
            v-if="notifications.data.length === 0"
            class="px-4 py-8 text-center text-sm text-muted-foreground"
        >
            No notifications yet.
        </li>
    </ul>

    <Pagination
        :meta="{
            current_page: notifications.current_page,
            last_page: notifications.last_page,
            from: notifications.from,
            to: notifications.to,
            total: notifications.total,
            links: notifications.links,
        }"
    />
</template>
