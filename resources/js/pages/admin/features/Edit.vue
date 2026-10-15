<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import FeatureController from '@/actions/App/Http/Controllers/Admin/FeatureController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    <!-- Why update.form(id): builds PUT /admin/features/{id} from the PHP route -->
    <Form
        v-bind="FeatureController.update.form(props.feature.id)"
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
                <Link :href="FeatureController.index()">Cancel</Link>
            </Button>
        </div>
    </Form>
</template>
