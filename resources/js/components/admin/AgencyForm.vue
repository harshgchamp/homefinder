<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { fieldClass } from '@/lib/fieldClass';
import type { Agency } from '@/types';

/**
 * One form for both pages: Create passes no agency, Edit passes one.
 */
const props = defineProps<{
    agency?: Agency;
}>();

// Why useForm: the checkbox and the Edit/Create switch are simpler with the
// form data in a reactive object (same approach as PropertyForm).
const form = useForm({
    name: props.agency?.name ?? '',
    email: props.agency?.email ?? '',
    phone: props.agency?.phone ?? '',
    // Why default true: a new agency is usually live straight away (matches the model default).
    is_active: props.agency?.is_active ?? true,
});

const submit = (): void => {
    if (props.agency) {
        form.put(route('admin.agencies.update', props.agency.id), {
            preserveScroll: true,
        });
    } else {
        form.post(route('admin.agencies.store'), { preserveScroll: true });
    }
};
</script>

<template>
    <form class="max-w-md space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <input
                id="name"
                v-model="form.name"
                required
                maxlength="255"
                placeholder="e.g. Acme Homes"
                :class="fieldClass"
                :aria-invalid="!!form.errors.name || undefined"
                v-focus
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Email</Label>
            <input
                id="email"
                v-model="form.email"
                type="email"
                required
                maxlength="255"
                placeholder="hello@acme-homes.test"
                :class="fieldClass"
                :aria-invalid="!!form.errors.email || undefined"
            />
            <!-- Why: enquiry notifications for this agency are sent to this address -->
            <p class="text-xs text-muted-foreground">
                Enquiry notifications are sent here.
            </p>
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="phone">Phone</Label>
            <input
                id="phone"
                v-model="form.phone"
                type="tel"
                required
                maxlength="30"
                placeholder="020 7946 0000"
                :class="fieldClass"
                :aria-invalid="!!form.errors.phone || undefined"
            />
            <InputError :message="form.errors.phone" />
        </div>

        <div class="grid gap-2">
            <div class="flex items-center gap-2">
                <!-- Why not v-model: the checkbox can emit 'indeterminate'; we only store true/false -->
                <Checkbox
                    id="is_active"
                    :model-value="form.is_active"
                    @update:model-value="form.is_active = $event === true"
                />
                <Label for="is_active">Active</Label>
            </div>
            <p class="text-xs text-muted-foreground">
                Inactive agencies are kept with their properties but marked as
                not trading.
            </p>
            <InputError :message="form.errors.is_active" />
        </div>

        <div class="flex items-center gap-3">
            <!-- Why :disabled: stops a double click creating the agency twice -->
            <Button :disabled="form.processing">
                {{ agency ? 'Save changes' : 'Create agency' }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="route('admin.agencies.index')">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
