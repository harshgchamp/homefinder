<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { route } from 'ziggy-js';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: '/admin' },
            { title: 'Features', href: '/admin/features' },
            { title: 'Add', href: '/admin/features/create' },
        ],
    },
});
</script>

<template>
    <Head title="Add feature" />

    <Heading
        title="Add feature"
        description="The slug is generated from the name."
    />

    <!-- Why Form: Inertia submits it with XHR and supplies validation errors and a
         "processing" flag. route('admin.features.store') looks the URL up by route name. -->
    <Form
        :action="route('admin.features.store')"
        method="post"
        class="max-w-md space-y-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                name="name"
                required
                maxlength="100"
                placeholder="e.g. Garden"
                v-focus
            />
            <!-- Why: shows the message from StoreFeatureRequest next to the field -->
            <InputError :message="errors.name" />
        </div>

        <div class="flex items-center gap-3">
            <!-- Why :disabled: stops a double click creating the feature twice -->
            <Button :disabled="processing">Save</Button>
            <Button variant="ghost" as-child>
                <Link :href="route('admin.features.index')">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
