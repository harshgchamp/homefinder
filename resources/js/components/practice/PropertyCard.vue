<!--
    CHILD COMPONENT: PropertyCard
    A reusable card that shows one property. It doesn't know where the data comes from:
    the PARENT passes everything in through PROPS (like function arguments for a component),
    and the card tells the parent about user actions by EMITTING EVENTS.
    Used in: resources/js/pages/practice/Components.vue
-->
<script setup lang="ts">
// computed → derive display values (like the formatted price) from props.
import { computed } from 'vue';

/*
 * The "contract" of this component: which props it accepts and their types.
 * A `?` means OPTIONAL: the parent may leave it out.
 * Without `?`, the prop is REQUIRED: TypeScript (and Vue in dev mode) complains if it's missing.
 */
interface Props {
    id: number; // required: sent back to the parent in the `save` event so it knows WHICH card
    title: string; // required
    city: string; // required
    price: number; // required, and must be a NUMBER (see the parent for :price vs price)
    bedrooms?: number; // optional, falls back to the default below
    featured?: boolean; // optional, falls back to the default below
    tags?: string[]; // optional list of labels, e.g. ['Garden', 'Parking']
    saved?: boolean; // optional: the PARENT decides whether this card is saved
}

/*
 * defineProps<Props>() → declares the props from the interface. No import needed:
 *                        it's a compiler macro, available automatically in <script setup>.
 * withDefaults(…, {…}) → default values for the OPTIONAL props.
 * `props` is a READ-ONLY reactive object: props.title, props.price…
 * When the parent changes a value, props updates and this card re-renders automatically.
 *
 * (Vue 3.5+ alternative you may also see:
 *  const { title, bedrooms = 1, featured = false } = defineProps<Props>();)
 */
const props = withDefaults(defineProps<Props>(), {
    bedrooms: 1,
    featured: true,
    // Arrays/objects need a FUNCTION default, so each card gets its OWN new array
    // instead of all cards sharing one.
    tags: () => [],
    saved: false,
});

/*
 * defineEmits → declares which EVENTS this component can send UP to its parent.
 * Also a compiler macro (no import). The type reads as:
 *   event name  →  [list of arguments sent with it]
 * So `save: [id: number]` means: emit('save', someNumber).
 * TypeScript then rejects emit('sav', …) (typo) or emit('save', 'abc') (wrong type).
 * It returns the `emit` function we call to fire the event.
 */
const emit = defineEmits<{
    save: [id: number];
    viewDetails: [id: number]; // ← new: "the user wants to see this property", sends its id
}>();

// Called when the heart button is clicked.
function onSaveClick() {
    // Fire the `save` event and send this card's id along with it.
    // The card does NOT change any data itself: it only REPORTS "the user clicked save on card X".
    // The parent listens with @save and decides what to do.
    emit('save', props.id);
}

// Props are reactive, so a computed built from them updates when the parent changes the price.
// In <script> we read props.price (no .value, because props is a reactive object, not a ref).
const formattedPrice = computed(() => `£${props.price.toLocaleString()}`);

/*
 * ONE-WAY DATA FLOW: data goes DOWN from parent to child, never back up through props.
 * This line would FAIL (TypeScript error + Vue warning "Attempted to mutate prop"):
 *     props.saved = true;
 * The parent OWNS the data. To ask the parent to change it, the child EMITS an event.
 *   props DOWN ↓   events UP ↑
 */
</script>

<template>
    <!--
        In the template, props are available DIRECTLY by name: {{ title }}, not {{ props.title }}.
        (Both work, but the short form is the convention.)
        :class → yellow border only when the featured prop is true.
    -->
    <div
        class="rounded-lg border p-3"
        :class="{ 'border-yellow-500': featured }"
    >
        <!-- Title on the left, save button on the right -->
        <div class="flex items-start justify-between gap-2">
            <button
                class="text-left font-medium hover:underline"
                @click="emit('viewDetails', id)"
            >
                {{ title }}
            </button>

            <!--
                @click → our function, which emits `save` to the parent.
                The heart's look depends on the `saved` PROP, which the parent controls.
                So: click → emit UP → parent updates its data → new `saved` prop comes DOWN → heart fills.
                (You could also emit inline: @click="emit('save', id)")
            -->
            <button
                class="text-lg leading-none"
                :class="saved ? 'text-red-500' : 'text-muted-foreground'"
                :aria-label="saved ? 'Unsave property' : 'Save property'"
                @click="onSaveClick"
            >
                <!-- ♥ filled when saved, ♡ outline when not -->
                {{ saved ? '♥' : '♡' }}
            </button>
        </div>

        <p class="text-sm text-muted-foreground">
            {{ city }} · {{ bedrooms }} bed
        </p>
        <!-- The computed from <script> -->
        <p class="mt-1 font-semibold">{{ formattedPrice }}</p>

        <!-- A prop can drive v-if like any other value -->
        <span
            v-if="featured"
            class="mt-2 inline-block rounded bg-yellow-100 px-2 text-xs dark:bg-yellow-900"
        >
            Featured
        </span>

        <!-- One badge per tag. An empty array (the default) renders nothing. -->
        <div class="mt-2 flex flex-wrap gap-1">
            <span
                v-for="tag in tags"
                :key="tag"
                class="rounded bg-muted px-2 text-xs"
            >
                {{ tag }}
            </span>
        </div>
    </div>
</template>
