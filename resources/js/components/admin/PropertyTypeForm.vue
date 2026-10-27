<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { route } from 'ziggy-js';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fieldClass } from '@/lib/fieldClass';
import type { PropertyType } from '@/types';

/**
 * ONE form for both pages:
 *   Create.vue  ->  <PropertyTypeForm />                  (no prop: create mode)
 *   Edit.vue    ->  <PropertyTypeForm :type="type" />     (prop given: edit mode)
 *
 * The optional prop IS the switch. Nothing else (no "mode" prop) is needed,
 * so the two can never disagree (e.g. mode="edit" but no type to edit).
 */
const props = defineProps<{
    // Why "?": optional. undefined on Create, the existing row on Edit.
    type?: PropertyType;
}>();

// Why computed: one readable name for the switch, used in the template below.
const isEditing = computed(() => props.type !== undefined);

// Why "?? ''": on Create there is no type, so start empty;
// on Edit, start with the saved name so the user can change it.
// useForm copies these values once; it does not follow later prop changes.
const form = useForm({
    name: props.type?.name ?? '',
});

const submit = (): void => {
    // Why "if (props.type)" and not "if (isEditing.value)": TypeScript only knows
    // props.type is defined INSIDE this if when the check is on props.type itself.
    // Then props.type.id below needs no "!" or "?".
    if (props.type) {
        // Edit: PUT /admin/property-types/{id}  ->  PropertyTypeController@update
        form.put(route('admin.property-types.update', props.type.id), {
            preserveScroll: true,
        });
    } else {
        // Create: POST /admin/property-types  ->  PropertyTypeController@store
        form.post(route('admin.property-types.store'), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <form class="max-w-md space-y-6" @submit.prevent="submit">
        <div class="grid gap-2">
            <Label for="name">Name</Label>
            <!-- Why v-focus only on Create: on Edit the user may just be looking -->
            <input
                id="name"
                v-model="form.name"
                v-focus="!isEditing"
                required
                maxlength="100"
                placeholder="e.g. Bungalow"
                :class="fieldClass"
                :aria-invalid="!!form.errors.name || undefined"
            />
            <!-- Same error slot for both modes: Store/UpdatePropertyTypeRequest share the messages -->
            <InputError :message="form.errors.name" />
        </div>

        <!-- Only Edit has something useful to say here -->
        <p v-if="type" class="text-sm text-muted-foreground">
            Used by {{ type.properties_count ?? 0 }}
            {{ type.properties_count === 1 ? 'property' : 'properties' }}.
            Renaming changes it everywhere; the slug is updated too.
        </p>

        <div class="flex items-center gap-3">
            <!-- Why :disabled: stops a double click from saving twice -->
            <Button :disabled="form.processing">
                {{ isEditing ? 'Save changes' : 'Create property type' }}
            </Button>
            <Button variant="ghost" as-child>
                <Link :href="route('admin.property-types.index')">Cancel</Link>
            </Button>
        </div>
    </form>
</template>
