<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import AppLogo from '@/components/AppLogo.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import type { User } from '@/types';

const page = usePage();

// Why the cast: the shared Auth type says user is always set, but on public
// pages a guest has no user, so treat it as possibly null here.
const user = computed(() => page.props.auth.user as User | null);
</script>

<template>
    <div class="min-h-screen bg-background">
        <header class="border-b">
            <div
                class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4"
            >
                <Link :href="route('home')" class="flex items-center">
                    <AppLogo />
                </Link>

                <nav class="flex items-center gap-2">
                    <Button v-if="user" variant="outline" as-child>
                        <Link :href="route('dashboard')">Dashboard</Link>
                    </Button>
                    <template v-else>
                        <Button variant="ghost" as-child>
                            <Link :href="route('login')">Log in</Link>
                        </Button>
                        <Button as-child>
                            <Link :href="route('register')">Register</Link>
                        </Button>
                    </template>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8">
            <slot />
        </main>

        <!-- Why here too: flash toasts ("Enquiry sent") need a Toaster on the page,
             and the app layouts that normally provide it are not used on public pages. -->
        <Toaster />
    </div>
</template>
