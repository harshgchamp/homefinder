<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

// Why computed + adminOnly: agents use the admin area too, but features and
// notifications are site-wide (the "manage-site" gate), so their links are hidden.
const isAdmin = computed(() => page.props.auth.user?.is_admin === true);

const links = computed(() =>
    [
        { title: 'Dashboard', href: '/admin', adminOnly: false },
        { title: 'Properties', href: '/admin/properties', adminOnly: false },
        { title: 'Agencies', href: '/admin/agencies', adminOnly: true },
        { title: 'Features', href: '/admin/features', adminOnly: true },
        {
            title: 'Property types',
            href: '/admin/property-types',
            adminOnly: true,
        },
        {
            title: 'Notifications',
            href: '/admin/notifications',
            adminOnly: true,
        },
        // Property types: added in a later step
    ].filter((link) => !link.adminOnly || isAdmin.value),
);

// Why: /admin/features/create and /admin/features/5/edit should also highlight "Features".
// The dashboard link is matched exactly, otherwise it would be active on every admin page.
const isActive = (href: string): boolean =>
    href === '/admin' ? page.url === href : page.url.startsWith(href);
</script>

<template>
    <div class="flex flex-col gap-6 p-4">
        <nav class="flex gap-2 border-b pb-3" aria-label="Admin navigation">
            <Link
                v-for="link in links"
                :key="link.href"
                :href="link.href"
                class="rounded-md px-3 py-1.5 text-sm transition-colors hover:bg-muted"
                :class="{ 'bg-muted font-medium': isActive(link.href) }"
            >
                {{ link.title }}
                <!-- Unread count, shared on every page by HandleInertiaRequests -->
                <span
                    v-if="
                        link.href === '/admin/notifications' &&
                        page.props.unreadNotificationsCount > 0
                    "
                    class="ml-1 rounded-full bg-primary px-1.5 text-xs text-primary-foreground tabular-nums"
                    >{{ page.props.unreadNotificationsCount }}</span
                >
            </Link>
        </nav>

        <slot />
    </div>
</template>
