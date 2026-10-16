<script setup lang="ts" generic="T extends string | number">
import { fieldClass } from '@/lib/fieldClass';
import type { Option } from '@/types';

// Why defineModel: the parent writes v-model="filters.city" and Vue wires up
// the modelValue prop and the update:modelValue event for us.
const model = defineModel<T | null>({ required: true });

defineProps<{
    options: Option<T>[];
    /** Text for the empty choice, e.g. "Any agency". Leave out to force a choice. */
    placeholder?: string;
    id?: string;
    name?: string;
    invalid?: boolean;
}>();
</script>

<template>
    <!-- Why :value (not value): keeps numbers as numbers, so agency_id stays 3, not "3" -->
    <select
        :id="id"
        v-model="model"
        :name="name"
        :class="fieldClass"
        :aria-invalid="invalid || undefined"
    >
        <option v-if="placeholder !== undefined" :value="null">
            {{ placeholder }}
        </option>
        <option
            v-for="option in options"
            :key="option.value"
            :value="option.value"
        >
            {{ option.label }}
        </option>
    </select>
</template>
