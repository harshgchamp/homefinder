<!--
    CHILD COMPONENT: SearchInput
    A reusable search box that supports v-model, so a parent can write:
        <SearchInput v-model="search" />
    and `search` stays in sync both ways, just like a plain <input v-model>.
    Used in: resources/js/pages/practice/Components.vue
-->
<script setup lang="ts">
/*
 * defineModel<string>() → (Vue 3.4+) creates a ref that is wired to the parent's v-model.
 * - READING model.value gives the parent's current value (it arrived as the `modelValue` prop).
 * - WRITING model.value = 'x' tells the parent to update (Vue emits `update:modelValue` for you).
 * The child never owns the data: writing to the model just ASKS the parent to change it,
 * so one-way data flow (props down, events up) still holds. defineModel hides the boilerplate.
 *
 * { default: '' } → used if the parent doesn't bind v-model at all.
 * Compiler macro like defineProps/defineEmits, so no import needed.
 */
const model = defineModel<string>({ default: '' });

// A normal prop alongside the model: v-model and props work together.
withDefaults(defineProps<{ placeholder?: string }>(), {
    placeholder: 'Search…',
});

/*
 * The SAME component written the long way (before Vue 3.4, still common in older code
 * and a classic interview question):
 *
 *   const props = defineProps<{ modelValue: string }>();
 *   const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
 *
 *   <input
 *       :value="props.modelValue"
 *       @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
 *   />
 */

// Clears the box. Assigning to model.value emits update:modelValue('') → the parent's ref becomes ''.
function clear() {
    model.value = '';
}
</script>

<template>
    <!-- relative → lets the clear button be positioned inside the input's box -->
    <div class="relative">
        <!--
            v-model="model" → bind the inner <input> to our model ref.
            Typing → model.value changes → the parent's `search` updates.
            The parent changes `search` → model.value changes → this input shows the new text.
            :placeholder → the prop from the parent (or the default 'Search…').
            pr-8 → right padding so typed text doesn't run under the × button.
        -->
        <input
            v-model="model"
            type="search"
            class="w-full rounded border px-2 py-1 pr-8"
            :placeholder="placeholder"
        />

        <!-- Show the × only when there's text (an empty string is falsy). Clicking it clears the parent's value too. -->
        <button
            v-if="model"
            type="button"
            class="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground"
            aria-label="Clear search"
            @click="clear"
        >
            ×
        </button>
    </div>
</template>
