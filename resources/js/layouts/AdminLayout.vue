<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();

const links = [
    { title: 'Dashboard', href: '/admin' },
    { title: 'Properties', href: '/admin/properties' },
    { title: 'Features', href: '/admin/features' },
    // Property types, Agencies: added in the next steps
];

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
            </Link>
        </nav>

        <slot />
    </div>
</template>
