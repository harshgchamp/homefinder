<!--
    CHILD COMPONENT: PriceRange
    Two number inputs (min and max) with TWO separate v-models, so a parent can write:
        <PriceRange v-model:min="minPrice" v-model:max="maxPrice" />
    Used in: resources/js/pages/practice/Components.vue
-->
<script setup lang="ts">
/*
 * NAMED models: the first argument is the model's NAME.
 *   defineModel('min') pairs with v-model:min="…" in the parent
 *   defineModel('max') pairs with v-model:max="…" in the parent
 * Without a name, defineModel() pairs with plain v-model (modelValue). A component can
 * have only ONE unnamed model, which is why multiple models need names.
 *
 * <number> → the value's type. The default must match it (0, not '').
 * Under the hood each one is a prop (min / max) + an event (update:min / update:max).
 */
const min = defineModel<number>('min', { default: 0 });
const max = defineModel<number>('max', { default: 0 });
</script>

<template>
    <!-- One row: [min] – [max] -->
    <div class="flex items-center gap-2">
        <!--
            v-model.number="min" → typing updates the parent's minPrice.
            .number converts the input's string to a number (inputs always give strings).
            step="10000" → the arrow keys move in £10k steps.
        -->
        <input
            v-model.number="min"
            type="number"
            step="10000"
            class="w-36 rounded border px-2 py-1"
            placeholder="Min £"
            aria-label="Minimum price"
        />

        <!-- Just a visual separator -->
        <span>–</span>

        <!-- Same for max: bound to the parent's maxPrice via v-model:max -->
        <input
            v-model.number="max"
            type="number"
            step="10000"
            class="w-36 rounded border px-2 py-1"
            placeholder="Max £"
            aria-label="Maximum price"
        />
    </div>
</template>
