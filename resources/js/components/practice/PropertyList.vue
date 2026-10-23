<!--
    CHILD COMPONENT: PropertyList
    Demonstrates a SCOPED slot. The CHILD owns the loop (v-for), but the PARENT decides
    how each row LOOKS. The child hands each item back UP to the parent's template
    through slot props.
    Used in: resources/js/pages/practice/Components.vue
-->
<script setup lang="ts">
// The minimum shape this list needs from each item.
interface ListItem {
    id: number;
    title: string;
    city: string;
    price: number;
}

// The parent passes the array in as a normal prop.
defineProps<{
    items: ListItem[];
}>();
</script>

<template>
    <!-- divide-y → a thin line between rows -->
    <ul class="divide-y rounded-lg border">
        <!-- The CHILD does the looping… -->
        <li
            v-for="(item, index) in items"
            :key="item.id"
            class="px-3 py-2 text-sm"
        >
            <!--
                …and hands each item UP to the parent with SLOT PROPS:
                :property="item" → the parent can read it as `property`
                :index="index"   → and as `index`
                This is a "scoped" slot: the parent's slot content can see the CHILD's data.
                Fallback (shown if the parent passes no slot): just the title.
            -->
            <slot :property="item" :index="index">
                {{ item.title }}
            </slot>
        </li>
    </ul>
</template>
