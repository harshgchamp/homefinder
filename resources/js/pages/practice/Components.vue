<!--
    LESSON 3: Components
    Built up one topic at a time. This page is the PARENT; the reusable
    pieces (children) live in resources/js/components/practice/.
-->
<script setup lang="ts">
// Head → tab title. Link → Inertia navigation without a full page reload.
import { Head, Link } from '@inertiajs/vue3';

// ref → reactive value. When the parent's data changes, children re-render with the new props.
import { ref, computed } from 'vue';

// Ziggy: route name → URL.
import { route } from 'ziggy-js';

/*
 * Importing a component = registering it. In <script setup>, just importing
 * makes <PropertyCard> usable in the template, with no `components: {}` option needed.
 * '@/' is an alias for resources/js/ (set up in vite/tsconfig).
 */
import PropertyCard from '@/components/practice/PropertyCard.vue';
import SearchInput from '@/components/practice/SearchInput.vue';
import PriceRange from '@/components/practice/PriceRange.vue';
import BaseCard from '@/components/practice/BaseCard.vue';
import PropertyList from '@/components/practice/PropertyList.vue';
import BaseModal from '@/components/practice/BaseModal.vue';

// Breadcrumbs shown by AppLayout: "Practice > Components".
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Practice', href: route('practice.index') },
            { title: 'Components', href: route('practice.components') },
        ],
    },
});

/* ------------------------------------------------------------------ */
/* Topic 1: props (parent → child)                                    */
/* ------------------------------------------------------------------ */

/*
 * The PARENT owns the data. It's a ref because we change it below (raisePrices),
 * and every card must update when it does.
 * Later (Day 3) this array will arrive from Laravel as an Inertia prop instead.
 */
const properties = ref([
    {
        id: 1,
        title: 'Riverside flat',
        city: 'Leeds',
        price: 210000,
        bedrooms: 2,
        featured: true,
    },
    {
        id: 2,
        title: 'Victorian terrace',
        city: 'York',
        price: 345000,
        bedrooms: 3,
        featured: false,
    },
    {
        id: 3,
        title: 'Seaside bungalow',
        city: 'Whitby',
        price: 275000,
        bedrooms: 2,
        featured: false,
    },
]);

// Changes the PARENT's data. The new prices flow DOWN into every card automatically.
// This is one-way data flow: parent changes data → children receive new props → re-render.
function raisePrices() {
    // forEach → visit each property. Changing a field on a reactive object is tracked.
    properties.value.forEach((property) => {
        property.price += 10000;
    });
}

/* ------------------------------------------------------------------ */
/* Topic 2: emits (child → parent)                                    */
/* ------------------------------------------------------------------ */

// The PARENT owns the "which properties are saved" state: a list of ids.
// The cards never change this directly; they emit `save` and the parent updates it here.
const savedIds = ref<number[]>([]);

/*
 * Runs when ANY card emits `save`. Vue passes the event's arguments to the handler,
 * so `id` is the number the card sent with emit('save', props.id).
 * Clicking again on a saved card unsaves it (toggle).
 */
function toggleSaved(id: number) {
    if (savedIds.value.includes(id)) {
        // Already saved → remove it. filter() keeps every id EXCEPT this one.
        savedIds.value = savedIds.value.filter((savedId) => savedId !== id);
    } else {
        // Not saved yet → add it to the end of the list.
        savedIds.value.push(id);
    }
}

// Which property the user is viewing. null = none yet.
const selectedId = ref<number | null>(null);

// Runs when a card emits viewDetails. Vue passes the id the card sent.
function showDetails(id: number) {
    selectedId.value = id;
}

// Your bonus: the full property object for selectedId.
// find() returns the first match, or undefined if none (e.g. id 99 isn't in the array),
// which is why the template uses selectedProperty?.title.
const selectedProperty = computed(() =>
    properties.value.find((p) => p.id === selectedId.value),
);

/* ------------------------------------------------------------------ */
/* Topic 3: v-model on a component (defineModel)                      */
/* ------------------------------------------------------------------ */

// The PARENT owns the search text. <SearchInput v-model="search" /> keeps it in sync both ways.
const search = ref('');

// Your exercise: the parent owns BOTH ends of the range, each with its own v-model.
// Numbers (not strings), to match defineModel<number> in PriceRange.
const minPrice = ref(0);
const maxPrice = ref(5000000);

// Uses the search text to filter the property titles, showing the value really is the parent's.
const matchingTitles = computed(
    () =>
        properties.value
            .filter((p) =>
                p.title.toLowerCase().includes(search.value.toLowerCase()),
            )
            .map((p) => p.title), // map() turns each property object into just its title string
);

/* ------------------------------------------------------------------ */
/* Topic 5: modal                                                     */
/* ------------------------------------------------------------------ */

// 5a. A simple info modal: just open/close.
const isInfoOpen = ref(false);

// 5b. The delete confirmation modal.
const isDeleteOpen = ref(false);

// Which property the user wants to delete. null = none chosen yet.
const deleteId = ref<number | null>(null);

// The full object for deleteId, so the modal can show its title.
const propertyToDelete = computed(() =>
    properties.value.find((p) => p.id === deleteId.value),
);

// Step 1: the "Delete" button on a row. Remember WHICH property, then open the modal.
// Nothing is deleted yet: the user must confirm first.
function askToDelete(id: number) {
    deleteId.value = id;
    isDeleteOpen.value = true;
}

// Step 2: the modal's "Delete" button. Actually remove it, then close the modal.
function confirmDelete() {
    // filter() keeps every property EXCEPT the one being deleted.
    // Because `properties` is the parent's shared data, it disappears from
    // EVERY section on the page (cards, lists, search), all at once.
    properties.value = properties.value.filter((p) => p.id !== deleteId.value);

    // Also forget it in the other state that referenced it.
    savedIds.value = savedIds.value.filter((id) => id !== deleteId.value);

    isDeleteOpen.value = false;
    deleteId.value = null;
}
</script>

<template>
    <!-- Browser tab: "Components - <app name>" -->
    <Head title="Components" />

    <!-- Page wrapper -->
    <div class="flex max-w-4xl flex-col gap-8 p-4">
        <!-- Back to the lesson list -->
        <Link
            :href="route('practice.index')"
            class="text-sm text-muted-foreground hover:underline"
        >
            &larr; All lessons
        </Link>

        <!-- ============================================================ -->
        <!-- 1. Props: parent → child                                     -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-4 font-semibold">1. Props (parent → child)</h2>

            <!-- ---------- 1a. One card, values written by hand ---------- -->
            <h3 class="mb-2 text-sm font-medium">1a. Passing props by hand</h3>
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <!--
                    Static vs dynamic props:
                    title="Garden cottage" → NO colon: always passes a STRING.
                    :price="410000"        → WITH colon: evaluated as JavaScript, so a NUMBER.
                    price="410000" (no colon) would pass the string "410000", and TypeScript
                    reports an error because the card expects a number.
                    :featured="true" passes the boolean true. Just writing `featured` also means true.
                    bedrooms is left out, so the card uses its default (1).
                -->
                <PropertyCard
                    :id="99"
                    title="Garden cottage"
                    city="York"
                    :price="410000"
                    :featured="true"
                    :tags="['Garden', 'Parking']"
                    :saved="savedIds.includes(99)"
                    @save="toggleSaved"
                    @view-details="showDetails"
                />
            </div>

            <!-- ---------- 1b. Cards from a list ---------- -->
            <h3 class="mb-2 text-sm font-medium">
                1b. One card per item (v-for + props)
            </h3>

            <!-- Changes the parent's data → every card updates -->
            <button class="mb-3 rounded border px-3 py-1" @click="raisePrices">
                Raise all prices by £10k (parent changes data)
            </button>

            <div class="grid gap-3 sm:grid-cols-3">
                <!--
                    v-for works on components exactly like on elements, and :key is required.
                    Each prop is bound with : to a field of the current item.
                    kebab-case or camelCase both work for prop names in templates
                    (:is-featured / :isFeatured); this project uses camelCase.
                -->
                <PropertyCard
                    v-for="property in properties"
                    :key="property.id"
                    :title="property.title"
                    :city="property.city"
                    :price="property.price"
                    :bedrooms="property.bedrooms"
                    :featured="property.featured"
                    :id="property.id"
                    :saved="savedIds.includes(property.id)"
                    @save="toggleSaved"
                    @view-details="showDetails"
                />
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- 2. Emits: child → parent                                     -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">2. Emits (child → parent)</h2>

            <!--
                Click any ♡ in section 1. The flow:
                1. The card calls emit('save', id)               → event goes UP
                2. The parent's @save="toggleSaved" runs          → parent updates savedIds
                3. :saved="savedIds.includes(id)" is re-evaluated → new prop goes DOWN
                4. The card re-renders with a filled ♥
                Every card shares ONE handler; the id in the event says which card it was.
            -->
            <p class="text-sm">
                Saved: <strong>{{ savedIds.length }}</strong>
                <!-- join(', ') turns [1, 3] into "1, 3". Only shown when something is saved. -->
                <span v-if="savedIds.length" class="text-muted-foreground">
                    (ids: {{ savedIds.join(', ') }})
                </span>
            </p>

            <p v-if="selectedId !== null" class="mt-2 text-sm">
                Viewing property
                <strong>#{{ selectedId }} {{ selectedProperty?.title }}</strong>
            </p>
            <p v-else class="mt-2 text-sm text-muted-foreground">
                Click a card title to view it.
            </p>

            <!-- The parent can change the state too: the cards update because it's passed down as a prop -->
            <button
                class="mt-2 rounded border px-3 py-1 disabled:opacity-50"
                :disabled="!savedIds.length"
                @click="savedIds = []"
            >
                Clear saved
            </button>
        </section>

        <!-- ============================================================ -->
        <!-- 3. v-model on a component                                    -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">3. v-model on a component</h2>

            <!--
                Works exactly like v-model on a plain <input>, but on OUR component.
                v-model="search" is shorthand for:
                    :modelValue="search"                      → value goes DOWN (a prop)
                    @update:modelValue="search = $event"      → new value comes UP (an event)
                So it's just props + emits combined, the two things you already know.
                placeholder is an ordinary prop passed alongside the v-model.
            -->
            <SearchInput
                v-model="search"
                placeholder="Search titles, e.g. terrace"
            />

            <!--
                Multiple v-models on ONE component: each gets a name after the colon.
                v-model:min="minPrice" = :min="minPrice" + @update:min="minPrice = $event"
            -->
            <h3 class="mt-4 mb-2 text-sm font-medium">
                Multiple v-models: PriceRange
            </h3>
            <PriceRange v-model:min="minPrice" v-model:max="maxPrice" />

            <!-- Shows the PARENT's values updating as you type in the child -->
            <p class="mt-2 text-sm">
                Range: £{{ minPrice.toLocaleString() }} – £{{
                    maxPrice.toLocaleString()
                }}
            </p>

            <!-- Proves the PARENT's `search` changes as you type inside the child -->
            <p class="mt-2 text-sm">
                Parent's search value: <strong>"{{ search }}"</strong>
            </p>

            <!-- The parent can also SET the value: the child's input updates (two-way) -->
            <button
                class="mt-2 rounded border px-3 py-1"
                @click="search = 'flat'"
            >
                Set search to "flat" from the parent
            </button>

            <!-- Filtered by the parent's computed, using the value typed in the child -->
            <ul class="mt-3 list-inside list-disc text-sm">
                <li v-for="title in matchingTitles" :key="title">
                    {{ title }}
                </li>
            </ul>
        </section>

        <!-- ============================================================ -->
        <!-- 4. Slots: the parent passes TEMPLATE into the child          -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-4 font-semibold">4. Slots</h2>

            <div class="grid gap-3 md:grid-cols-3">
                <!-- ---------- 4a. Default slot ---------- -->
                <!--
                    Everything BETWEEN <BaseCard> and </BaseCard> goes into the child's
                    <slot /> (the default slot). It can be any HTML, even other components.
                    Note: this content is written in the PARENT, so it uses the PARENT's
                    data (savedIds here), not the child's.
                -->
                <BaseCard>
                    <p class="font-medium">4a. Default slot</p>
                    <p>This text is written in the parent.</p>
                    <p>Saved properties: {{ savedIds.length }}</p>
                </BaseCard>

                <!-- ---------- 4b. Named slots ---------- -->
                <!--
                    <template #header> → fills the child's <slot name="header" />.
                    #header is shorthand for v-slot:header (like @ for v-on, : for v-bind).
                    Content NOT inside a named <template> goes to the default slot.
                -->
                <BaseCard>
                    <template #header>4b. Named slots</template>

                    Header, body and footer are all decided by the parent.

                    <template #footer>
                        <button class="rounded border px-3 py-1 text-sm">
                            Cancel
                        </button>
                        <button
                            class="rounded bg-primary px-3 py-1 text-sm text-primary-foreground"
                        >
                            Save
                        </button>
                    </template>
                </BaseCard>

                <!-- ---------- 4c. Fallback content ---------- -->
                <!--
                    We pass only a header, no body. The child's default slot is empty,
                    so its FALLBACK ("Nothing to show.") appears. No footer is passed,
                    so $slots.footer is undefined and the footer strip isn't rendered at all.
                -->
                <BaseCard>
                    <template #header>4c. Fallback content</template>
                </BaseCard>
            </div>

            <!-- ---------- 4d. Scoped slot ---------- -->
            <h3 class="mt-5 mb-2 text-sm font-medium">
                4d. Scoped slot: the child loops, the parent styles each row
            </h3>
            <div class="grid gap-3 md:grid-cols-2">
                <!-- Without any slot content → the child's fallback (just the title) is used -->
                <PropertyList :items="properties" />

                <!--
                    #default="{ property, index }" → receive the slot props the child sent
                    with <slot :property="item" :index="index">. The { } destructures them.
                    Now the PARENT decides how each row looks, using the CHILD's data.
                    The same list component could render rows completely differently
                    on another page, without changing PropertyList.vue at all.
                -->
                <PropertyList :items="properties">
                    <template #default="{ property, index }">
                        <div class="flex justify-between">
                            <span
                                >{{ index + 1 }}. {{ property.title }} ({{
                                    property.city
                                }})</span
                            >
                            <strong
                                >£{{ property.price.toLocaleString() }}</strong
                            >
                        </div>
                    </template>
                </PropertyList>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- 5. Modal                                                     -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-4 font-semibold">5. Modal</h2>

            <!-- ---------- 5a. Simple modal ---------- -->
            <h3 class="mb-2 text-sm font-medium">5a. Open / close</h3>
            <!-- The PARENT opens it by setting its own ref; v-model passes it down -->
            <button
                class="mb-5 rounded border px-3 py-1"
                @click="isInfoOpen = true"
            >
                Open info modal
            </button>

            <!--
                v-model="isInfoOpen" → the modal reads it to show/hide, and sets it to false
                when closed (×, ESC or backdrop click), so the parent's ref stays correct.
                title → a normal prop. The text inside → the default slot (the body).
                The modal's markup sits HERE in the code, but Teleport renders it at the
                end of <body>.
            -->
            <BaseModal v-model="isInfoOpen" title="About this property">
                <p>
                    Built in 1890, fully renovated in 2024. Close me with ×,
                    ESC, or by clicking the dark backdrop.
                </p>
            </BaseModal>

            <!-- ---------- 5b. Delete confirmation ---------- -->
            <h3 class="mb-2 text-sm font-medium">
                5b. Confirm before deleting
            </h3>

            <ul class="divide-y rounded-lg border text-sm">
                <li
                    v-for="property in properties"
                    :key="property.id"
                    class="flex items-center justify-between px-3 py-2"
                >
                    <span>{{ property.title }}</span>
                    <!-- Doesn't delete: only asks (opens the modal for this id) -->
                    <button
                        class="rounded border border-red-300 px-2 py-0.5 text-red-600"
                        @click="askToDelete(property.id)"
                    >
                        Delete
                    </button>
                </li>
            </ul>
            <!-- Empty state once everything is deleted -->
            <p
                v-if="!properties.length"
                class="mt-2 text-sm text-muted-foreground"
            >
                All properties deleted. Reload the page to get them back.
            </p>

            <BaseModal v-model="isDeleteOpen" title="Delete property?">
                <!-- ?. → safe if propertyToDelete is undefined -->
                <p>
                    Are you sure you want to delete
                    <strong>{{ propertyToDelete?.title }}</strong
                    >? This can't be undone.
                </p>

                <!--
                    #footer="{ close }" → receive the modal's close() through the SCOPED slot
                    (the modal wrote <slot name="footer" :close="close" />).
                    Cancel uses the modal's own close; Delete uses the parent's confirmDelete.
                -->
                <template #footer="{ close }">
                    <button class="rounded border px-3 py-1" @click="close">
                        Cancel
                    </button>
                    <button
                        class="rounded bg-red-600 px-3 py-1 text-white"
                        @click="confirmDelete"
                    >
                        Delete
                    </button>
                </template>
            </BaseModal>
        </section>

        <!-- Next topic sections are added below, one at a time. -->
    </div>
</template>
