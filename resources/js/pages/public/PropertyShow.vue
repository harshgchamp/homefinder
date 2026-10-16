<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fieldClass } from '@/lib/fieldClass';
import type { Property, User } from '@/types';

const props = defineProps<{
    property: Property;
    enquiredRecently: boolean;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user as User | null);

// Why: the route has the "verified" middleware, so an unverified user would be
// redirected away. Better to explain that here than show a form that fails.
const canEnquire = computed(
    () => user.value !== null && user.value.email_verified_at !== null,
);

const form = useForm({
    message: '',
    phone: '',
});

const submit = (): void => {
    form.post(route('properties.enquiries.store', props.property.slug), {
        preserveScroll: true,
        // Why: after a successful send, clear the form. The server then sets
        // enquiredRecently = true, so the "already sent" note replaces the form.
        onSuccess: () => form.reset(),
    });
};

const hideBrokenImage = (event: Event): void => {
    (event.target as HTMLImageElement).style.visibility = 'hidden';
};
</script>

<template>
    <Head :title="property.title" />

    <div class="grid gap-8 lg:grid-cols-3">
        <article class="space-y-6 lg:col-span-2">
            <div
                v-if="property.images?.length"
                class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            >
                <div
                    v-for="(image, index) in property.images"
                    :key="image.id"
                    class="overflow-hidden rounded-lg bg-muted"
                    :class="
                        index === 0
                            ? 'col-span-2 aspect-[16/9] sm:col-span-3'
                            : 'aspect-[4/3]'
                    "
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

            <header class="space-y-2">
                <div class="flex flex-wrap gap-2">
                    <Badge>{{ property.listing_type.label }}</Badge>
                    <Badge variant="secondary">{{
                        property.status.label
                    }}</Badge>
                </div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ property.title }}
                </h1>
                <p class="text-muted-foreground">
                    {{ property.address_line_1 }}, {{ property.city }}
                    {{ property.postcode }}
                </p>
                <p class="text-2xl font-semibold tabular-nums">
                    {{ property.formatted_price }}
                </p>
            </header>

            <dl class="flex flex-wrap gap-6 rounded-xl border p-4">
                <div>
                    <dt class="text-sm text-muted-foreground">Bedrooms</dt>
                    <dd class="font-medium">{{ property.bedrooms }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Bathrooms</dt>
                    <dd class="font-medium">{{ property.bathrooms }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Type</dt>
                    <dd class="font-medium">
                        {{ property.property_type?.name }}
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Agency</dt>
                    <dd class="font-medium">{{ property.agency?.name }}</dd>
                </div>
            </dl>

            <p class="whitespace-pre-line text-muted-foreground">
                {{ property.description }}
            </p>

            <div v-if="property.features?.length" class="space-y-2">
                <h2 class="font-medium">Features</h2>
                <div class="flex flex-wrap gap-2">
                    <Badge
                        v-for="feature in property.features"
                        :key="feature.id"
                        variant="outline"
                        >{{ feature.name }}</Badge
                    >
                </div>
            </div>
        </article>

        <!-- Enquiry box: one of four states, depending on who is looking -->
        <aside class="h-fit space-y-4 rounded-xl border p-4 lg:sticky lg:top-4">
            <h2 class="font-medium">Ask the agency</h2>

            <!-- 1. Guest -->
            <p v-if="!user" class="text-sm text-muted-foreground">
                <Link :href="route('login')" class="underline">Log in</Link>
                or
                <Link :href="route('register')" class="underline"
                    >create an account</Link
                >
                to send an enquiry.
            </p>

            <!-- 2. Logged in, email not verified -->
            <p v-else-if="!canEnquire" class="text-sm text-muted-foreground">
                Please verify your email address before sending an enquiry, so
                the agency can reply to you.
            </p>

            <!-- 3. Already sent one in the last 24 hours -->
            <p
                v-else-if="enquiredRecently"
                class="text-sm text-muted-foreground"
            >
                You sent an enquiry about this property recently. The agency
                will be in touch.
            </p>

            <!-- 4. Can send -->
            <form v-else class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="message">Message</Label>
                    <textarea
                        id="message"
                        v-model="form.message"
                        rows="5"
                        required
                        minlength="10"
                        maxlength="2000"
                        placeholder="I'd like to arrange a viewing…"
                        :class="[fieldClass, 'h-auto py-2']"
                        :aria-invalid="!!form.errors.message || undefined"
                    />
                    <InputError :message="form.errors.message" />
                </div>

                <div class="grid gap-2">
                    <Label for="phone">Phone (optional)</Label>
                    <input
                        id="phone"
                        v-model="form.phone"
                        type="tel"
                        maxlength="30"
                        :class="fieldClass"
                        :aria-invalid="!!form.errors.phone || undefined"
                    />
                    <InputError :message="form.errors.phone" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="form.processing"
                >
                    Send enquiry
                </Button>
            </form>
        </aside>
    </div>
</template>
