<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from 'ziggy-js';
import type { Feature } from '@/types';

// Why: a single plain object here, because the controller called resolve()
// on the resource instead of sending the { data: ... } wrapper.
const props = defineProps<{
    feature: Feature;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Features', href: '/admin/features' },
            { title: 'Edit', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Edit ${props.feature.name}`" />

    <Heading
        title="Edit feature"
        :description="`Slug: ${props.feature.slug}. Last updated ${props.feature.updated_at}.`"
    />

    <!-- Why method="put": Inertia sends a real PUT request (via XHR), which matches
         the resource route's update(). No _method field is needed without files. -->
    <Form
        :action="route('admin.features.update', props.feature.id)"
        method="put"
        class="max-w-md space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <!-- Why default-value, not v-model: the Form component reads the inputs itself -->
            <Input
                id="name"
                name="name"
                :default-value="props.feature.name"
                required
                maxlength="100"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="flex items-center gap-3">
            <Button :disabled="processing">Save changes</Button>
            <Button variant="ghost" as-child>
                <Link :href="route('admin.features.index')">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
