<!--
    CHILD COMPONENT: BaseCard
    A reusable card FRAME (border, padding, header area, footer area).
    It doesn't decide WHAT goes inside: the parent fills the gaps using SLOTS.
    Props pass DATA into a component; slots pass TEMPLATE (HTML, other components) into it.
    Used in: resources/js/pages/practice/Components.vue
-->
<script setup lang="ts">
// No script logic needed: everything comes from the parent through slots.
// $slots (used in the template below) is available automatically in every component.
</script>

<template>
    <!-- The frame: the same look everywhere this card is used -->
    <div class="rounded-lg border">
        <!--
            v-if="$slots.header" → only render the header strip if the parent actually
            passed a #header slot. $slots is an object listing the slots the parent filled.
            Without this check we'd get an empty bordered strip.
        -->
        <div v-if="$slots.header" class="border-b px-3 py-2 font-medium">
            <!--
                NAMED slot: <slot name="header" /> is a placeholder.
                Whatever the parent puts in <template #header>…</template> appears HERE.
            -->
            <slot name="header" />
        </div>

        <div class="p-3 text-sm">
            <!--
                DEFAULT slot: <slot /> with no name.
                Whatever the parent puts BETWEEN <BaseCard> and </BaseCard>
                (outside any named <template #…>) appears here.
                FALLBACK content: anything written inside <slot>…</slot> shows only
                when the parent passes nothing for this slot.
            -->
            <slot>
                <span class="text-muted-foreground">Nothing to show.</span>
            </slot>
        </div>

        <!-- Same pattern as the header: only render the footer strip if the parent filled it -->
        <div
            v-if="$slots.footer"
            class="flex justify-end gap-2 border-t px-3 py-2"
        >
            <slot name="footer" />
        </div>
    </div>
</template>
