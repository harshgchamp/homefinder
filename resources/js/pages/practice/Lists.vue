<!--
    LESSON 2: Conditionals, lists, computed & watch
    Built up one topic at a time. Each topic adds a numbered section below.
-->
<script setup lang="ts">
// Head → sets the browser tab title. Link → Inertia navigation without a full page reload.
import { Head, Link } from '@inertiajs/vue3';

// ref      → makes a single value reactive: when it changes, the template re-renders.
// computed → a value DERIVED from other reactive values, cached until they change.
// watch / watchEffect → run a SIDE EFFECT (log, save, API call) when reactive data changes.
import { computed, ref, watch, watchEffect } from 'vue';

// Ziggy: turns a Laravel route NAME into a URL, e.g. route('practice.lists') → '/practice/lists'.
import { route } from 'ziggy-js';

// Breadcrumbs shown by AppLayout: "Practice > Lists".
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Practice', href: route('practice.index') }, // links back to the lesson list
            { title: 'Lists', href: route('practice.lists') }, // current page
        ],
    },
});

/* ------------------------------------------------------------------ */
/* Topic 1: v-if / v-else-if / v-else                                 */
/* ------------------------------------------------------------------ */

// A union type: a Status can ONLY be one of these four exact strings.
// Defined once and reused by the ref AND the function, so they can't drift apart.
type Status = 'loading' | 'error' | 'ready' | 'empty';

// Simulates the states of a page that loads data. Starts as 'ready'.
// ref<Status> → TypeScript rejects anything else, e.g. status.value = 'Loading' is an error.
const status = ref<Status>('ready');

// Called by each status button: @click="checkStatus('error')".
// The parameter is typed as Status, so checkStatus('Error') (wrong case) fails
// the type check instead of silently falling through to v-else at runtime.
function checkStatus(newStatus: Status) {
    // In <script> a ref is read/written through .value.
    // Changing it makes Vue re-evaluate the v-if chain in the template.
    status.value = newStatus;
}

// Used to show/hide a GROUP of elements with <template v-if>.
const isLoggedIn = ref(true);

/* ------------------------------------------------------------------ */
/* Topic 2: v-if vs v-show                                            */
/* ------------------------------------------------------------------ */

// ONE boolean drives BOTH boxes, so the only difference between them is
// the directive (v-if vs v-show) and you can compare them side by side.
const showBoxes = ref(true);

// Called by the Toggle button. Flips true ↔ false, which shows/hides both boxes.
function toggleBoxes() {
    showBoxes.value = !showBoxes.value;
}

/* ------------------------------------------------------------------ */
/* Topic 3: v-for and :key                                            */
/* ------------------------------------------------------------------ */

// 3b. A simple array of strings.
// A plain const (not ref) because it never changes. Vue only needs to track data that changes.
const cityNames = ['Leeds', 'York', 'Hull', 'Bath'];

// 3c. The shape of one property, so TypeScript knows which fields exist.
interface Property {
    id: number; // unique id from the database, used as the :key
    title: string;
    city: string;
    price: number;
}

// 3c. An array of objects: the most common real-world v-for (rows from Laravel).
const properties: Property[] = [
    { id: 101, title: 'Riverside flat', city: 'Leeds', price: 210000 },
    { id: 102, title: 'Victorian terrace', city: 'York', price: 345000 },
    { id: 103, title: 'Seaside bungalow', city: 'Whitby', price: 275000 },
];

// 3d. ONE object, so we can loop over its properties (key → value).
const sampleProperty = {
    title: 'Riverside flat',
    city: 'Leeds',
    bedrooms: 2,
    price: 210000,
};

// 3e. The :key demo list. It's a ref because we ADD items to it, and Vue
// must re-render when it changes.
const cities = ref([
    { id: 1, name: 'Leeds' },
    { id: 2, name: 'York' },
    { id: 3, name: 'Hull' },
]);

// A plain `let`, not a ref: it's never shown in the template, so Vue doesn't
// need to track it. It just hands out the next unique id.
let nextCityId = 4;

// Names to use for new rows, picked in turn.
const extraCityNames = ['Bath', 'Derby', 'Ely', 'Wells', 'Ripon'];

// Called by the "Add city to top" button.
function addCityToTop() {
    // Pick the next name. % (modulo) wraps back to the start after the last name.
    const name = extraCityNames[(nextCityId - 4) % extraCityNames.length];

    // unshift() inserts at the START of the array. Vue detects array methods
    // (push, pop, shift, unshift, splice, sort, reverse) and re-renders automatically.
    // nextCityId++ uses the current value, THEN adds 1 for next time.
    cities.value.unshift({ id: nextCityId++, name });
}

/* ------------------------------------------------------------------ */
/* Topic 4: computed                                                  */
/* ------------------------------------------------------------------ */

// 4a. Two INPUT values the user can change (bound with v-model in the template).
const housePrice = ref(250000);
const depositPercent = ref(10);

/*
 * 4a. computed(() => ...) → a value CALCULATED from other reactive values.
 * - Vue notes which refs the function READS (housePrice, depositPercent).
 * - When either changes, depositAmount recalculates automatically.
 * - The result is CACHED: reading it 10 times runs the function once,
 *   until an input changes.
 * - It returns a read-only ref: in <script> use depositAmount.value, in the template just depositAmount.
 */
const depositAmount = computed(() => {
    // Logged so you can SEE in the console (F12) exactly when it recalculates.
    console.log('[computed] depositAmount recalculated');

    // e.g. 250000 * 10 / 100 = 25000
    return (housePrice.value * depositPercent.value) / 100;
});

// A computed can use ANOTHER computed: loan = price - deposit.
const loanAmount = computed(() => housePrice.value - depositAmount.value);

// Your exercise: a computed from a computed from a computed.
// 300 = 25 years × 12 months (simplified, no interest). Math.round → whole pounds.
const monthlyPayment = computed(() => Math.round(loanAmount.value / 300));

// 4a. The SAME deposit calculation as a plain METHOD, to compare with computed.
// A method called in the template runs on EVERY re-render, even when its inputs didn't change.
function depositMethod(): number {
    console.log('[method] depositMethod ran');

    return (housePrice.value * depositPercent.value) / 100;
}

// Clicking "Re-render" changes this unrelated value. The component re-renders:
// the METHOD logs again, the COMPUTED doesn't (its inputs didn't change → cached).
const renderCount = ref(0);

// 4b. The search box text.
const search = ref('');

/*
 * 4b. A filtered list: the most common use of computed.
 * Reads search.value, so it recalculates on every keystroke.
 * `properties` (from 3c) is a plain array, which is fine here: it never changes.
 * filter() returns a NEW array, so the original list is never modified.
 */
const filteredProperties = computed(() => {
    // Lowercase both sides so "york" matches "York".
    const term = search.value.toLowerCase();

    // Keep a property if its title OR city contains the search term.
    // An empty term matches everything, so all properties show at the start.
    return properties.filter(
        (property) =>
            property.title.toLowerCase().includes(term) ||
            property.city.toLowerCase().includes(term),
    );
});

/* ------------------------------------------------------------------ */
/* Topic 5: watch & watchEffect                                       */
/* ------------------------------------------------------------------ */

// 5a. The last few search changes, shown as a list in the template.
const searchHistory = ref<string[]>([]);

/*
 * 5a. watch(source, callback)
 * source   → WHAT to watch. `search` is a ref, so we pass it directly.
 *            (For a property of a reactive object you'd pass a getter: () => filters.search)
 * callback → runs when the source changes and receives (newValue, oldValue).
 * Lazy by default: it does NOT run on page load, only after the first change.
 */
watch(search, (newValue, oldValue) => {
    // unshift → newest entry at the top. Template strings `${}` build the text.
    searchHistory.value.unshift(`"${oldValue}" → "${newValue}"`);

    // Keep only the last 5 entries; setting length cuts off the rest.
    if (searchHistory.value.length > 5) {
        searchHistory.value.length = 5;
    }
});

// 5b. State for the fake "check rates with the server" side effect.
const isCheckingRates = ref(false);
const rateMessage = ref('');

/*
 * 5b. watch + { immediate: true }
 * immediate → ALSO run once straight away on page load, not only on change.
 * This is the classic watch use case: an ASYNC side effect (normally an API call)
 * triggered by an input. A computed can't do this, because a computed must return
 * a value synchronously and have no side effects.
 */
watch(
    housePrice,
    (price) => {
        isCheckingRates.value = true; // show "Checking…" in the template

        // setTimeout simulates a slow server request (800ms). In a real app this
        // would be fetch() / axios / an Inertia partial reload.
        setTimeout(() => {
            isCheckingRates.value = false;
            // Fake rule: cheaper homes get a better rate.
            rateMessage.value =
                price < 300000 ? '4.2% available' : '4.8% available';
        }, 800);
    },
    { immediate: true },
);

/*
 * 5c. watchEffect(fn)
 * - NO source list: Vue tracks every reactive value fn READS, automatically.
 * - Runs IMMEDIATELY once, then again whenever any of them change.
 * - No oldValue.
 * This reads search.value and filteredProperties.value, so typing in the
 * search box re-runs it. See the console.
 */
watchEffect(() => {
    console.log(
        `[watchEffect] search="${search.value}" → ${filteredProperties.value.length} results`,
    );
});
</script>

<template>
    <!-- Browser tab: "Lists - <app name>" -->
    <Head title="Lists" />

    <!-- Page wrapper: column layout, limited width, space between sections -->
    <div class="flex max-w-4xl flex-col gap-8 p-4">
        <!-- Back to the lesson list. :href evaluates route() as JavaScript. -->
        <Link
            :href="route('practice.index')"
            class="text-sm text-muted-foreground hover:underline"
        >
            &larr; All lessons
        </Link>

        <!-- ============================================================ -->
        <!-- 1. v-if / v-else-if / v-else                                 -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">1. Conditional rendering</h2>

            <!-- Row of buttons; flex-wrap lets them wrap on narrow screens -->
            <div class="mb-3 flex flex-wrap gap-2">
                <!--
                    @click="checkStatus('loading')" → on click, call checkStatus with this argument.
                    With parentheses we pass OUR value. Without them (@click="checkStatus")
                    Vue would pass the native click event instead.
                -->
                <button
                    class="rounded border px-3 py-1"
                    @click="checkStatus('loading')"
                >
                    Loading
                </button>
                <button
                    class="rounded border px-3 py-1"
                    @click="checkStatus('error')"
                >
                    Error
                </button>
                <button
                    class="rounded border px-3 py-1"
                    @click="checkStatus('ready')"
                >
                    Ready
                </button>
                <button
                    class="rounded border px-3 py-1"
                    @click="checkStatus('empty')"
                >
                    Empty
                </button>
            </div>

            <!--
                ONE chain: Vue checks the conditions top to bottom and renders only the FIRST
                match. The others are not in the DOM at all (check with Inspect).
                Rule: v-if, v-else-if and v-else must be direct siblings, one after another.
                Putting any element between them breaks the chain and Vue throws a compile error.
            -->
            <p v-if="status === 'loading'" class="text-blue-600">
                Loading properties…
            </p>
            <p v-else-if="status === 'error'" class="text-red-600">
                Something went wrong.
            </p>
            <p v-else-if="status === 'empty'" class="text-muted-foreground">
                This is empty.
            </p>
            <!-- v-else takes no value: it renders when nothing above matched (here: 'ready') -->
            <p v-else class="text-green-600">Properties loaded.</p>

            <!-- Inline handler is fine here: one tiny assignment. ! flips true ↔ false. -->
            <button
                class="mt-3 rounded border px-3 py-1"
                @click="isLoggedIn = !isLoggedIn"
            >
                <!-- Ternary picks the label from the current state -->
                {{ isLoggedIn ? 'Log out' : 'Log in' }}
            </button>

            <!--
                <template v-if> → applies ONE condition to SEVERAL elements without adding a
                wrapper <div>. <template> itself is never rendered; only its children appear.
                Useful when an extra div would break a flex/grid layout.
            -->
            <template v-if="isLoggedIn">
                <p class="mt-2">Welcome back!</p>
                <p class="text-sm text-muted-foreground">
                    You have 3 saved searches.
                </p>
            </template>
            <!-- v-else works after a <template v-if> too -->
            <p v-else class="mt-2">Please log in to save searches.</p>
        </section>

        <!-- ============================================================ -->
        <!-- 2. v-if vs v-show                                            -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">2. v-if vs v-show</h2>

            <!-- Instructions for the experiment -->
            <p class="mb-3 text-sm text-muted-foreground">
                Type something in BOTH inputs, click Toggle twice, and see which
                text survives. While hidden, Inspect (F12 → Elements) to see
                what's left in the DOM.
            </p>

            <!-- Calls toggleBoxes(); the label shows the current state -->
            <button class="mb-3 rounded border px-3 py-1" @click="toggleBoxes">
                Toggle ({{ showBoxes ? 'visible' : 'hidden' }})
            </button>

            <!-- Two equal columns from the md breakpoint up, stacked on mobile -->
            <div class="grid gap-3 md:grid-cols-2">
                <!--
                    v-if → when false, the element is DESTROYED: removed from the DOM, and any
                    child components are unmounted (their onUnmounted hooks run).
                    When true again, Vue creates it FRESH, so the typed text is gone.
                    While hidden, the Elements panel shows only an empty placeholder comment saying "v-if".
                    Cost: expensive to TOGGLE (destroy + rebuild), cheap if it starts false
                    (nothing is rendered at all).
                    Use for: conditions that rarely change, permissions (admin-only parts),
                    loading/error states, anything with v-else.
                -->
                <div v-if="showBoxes" class="rounded border p-3">
                    <p class="mb-1 font-medium">v-if box</p>
                    <!--
                        No v-model ON PURPOSE: the typed text lives only inside this DOM
                        element, so we can see whether the element survived or was recreated.
                    -->
                    <input
                        class="w-full rounded border px-2 py-1"
                        placeholder="Type here…"
                    />
                </div>

                <!--
                    v-show → the element ALWAYS stays in the DOM. Vue only toggles the CSS
                    style="display: none". The element is never destroyed, so the typed text survives.
                    Cost: cheap to TOGGLE, but always rendered once on page load, even if hidden.
                    Use for: things toggled OFTEN, like dropdowns, tabs, accordions, mobile menus.
                    Limits: v-show doesn't work on <template> (there's no element to put
                    display:none on) and it has no v-else / v-else-if.
                -->
                <div v-show="showBoxes" class="rounded border p-3">
                    <p class="mb-1 font-medium">v-show box</p>
                    <input
                        class="w-full rounded border px-2 py-1"
                        placeholder="Type here…"
                    />
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- 3. v-for and :key                                            -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-4 font-semibold">3. v-for &amp; :key</h2>

            <!-- ---------- 3a. Number range ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                3a. Counting: v-for="n in 5"
            </h3>
            <div class="mb-5 flex gap-2">
                <!--
                    v-for="n in 5" → repeat this <span> 5 times. n = 1, 2, 3, 4, 5.
                    It starts at 1, NOT 0 (unlike a JS for loop).
                    :key="n" → each repeated element needs a unique key; the numbers are unique.
                -->
                <span
                    v-for="n in 5"
                    :key="n"
                    class="rounded border px-3 py-1"
                    >{{ n }}</span
                >
            </div>

            <!-- ---------- 3b. Array of strings ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                3b. Array of strings: (city, index)
            </h3>
            <ul class="mb-5 list-inside list-disc text-sm">
                <!--
                    v-for="(city, index) in cityNames"
                    city  → the current item ('Leeds', then 'York', …)
                    index → its position, starting at 0. The second variable is optional:
                            v-for="city in cityNames" works too.
                    :key="city" → the names are unique, so the value itself is a stable key.
                -->
                <li v-for="(city, index) in cityNames" :key="city">
                    <!-- index + 1 → show 1-based numbers to humans -->
                    {{ index + 1 }}. {{ city }}
                </li>
            </ul>

            <!-- ---------- 3c. Array of objects ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                3c. Array of objects (the real-world case)
            </h3>
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <!--
                    Each `property` is one object, so we read its fields with a dot: property.title.
                    :key="property.id" → ALWAYS key database rows by their id.
                    This is exactly how you'll render Laravel/Inertia data on Day 3.
                -->
                <div
                    v-for="property in properties"
                    :key="property.id"
                    class="rounded border p-3"
                >
                    <p class="font-medium">{{ property.title }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ property.city }}
                    </p>
                    <!-- toLocaleString() → 210000 becomes 210,000 -->
                    <p class="text-sm font-semibold">
                        £{{ property.price.toLocaleString() }}
                    </p>
                </div>
            </div>

            <!-- ---------- 3d. Object properties ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                3d. Looping over an object: (value, key, index)
            </h3>
            <dl class="mb-5 grid grid-cols-[auto_1fr] gap-x-4 text-sm">
                <!--
                    v-for over an OBJECT → one pass per property.
                    value = the property's value ('Riverside flat')
                    key   = the property's name ('title')
                    index = position (0, 1, 2…)
                    Note the order: VALUE comes first, then key.

                    <template v-for> → repeats TWO elements (<dt> + <dd>) per pass without a
                    wrapper div, which would break the 2-column grid. The :key goes on <template>.
                -->
                <template
                    v-for="(value, key, index) in sampleProperty"
                    :key="key"
                >
                    <dt class="font-medium">{{ index }}. {{ key }}</dt>
                    <dd>{{ value }}</dd>
                </template>
            </dl>

            <!-- ---------- 3e. Why :key matters ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                3e. Why :key matters: index vs id
            </h3>
            <p class="mb-2 text-sm text-muted-foreground">
                Type a note next to "Leeds" in BOTH lists, then click "Add city
                to top".
            </p>

            <button class="mb-3 rounded border px-3 py-1" @click="addCityToTop">
                Add city to top
            </button>

            <div class="grid gap-3 md:grid-cols-2">
                <div class="rounded border p-3">
                    <p class="mb-1 font-medium text-red-600">✗ :key="index"</p>
                    <!--
                        WRONG for lists that change. After unshift, the new city gets index 0.
                        Vue sees "key 0 still exists, only its text changed", so it REUSES row 0's
                        DOM element and just swaps the label. The <input> isn't recreated, so your
                        typed note stays on row 0, next to the WRONG city.
                        The same bug hits checkboxes, child components and animations.
                    -->
                    <div
                        v-for="(city, index) in cities"
                        :key="index"
                        class="flex items-center gap-2 py-1"
                    >
                        <span class="w-14">{{ city.name }}</span>
                        <!-- No v-model on purpose: the note lives only in the DOM element -->
                        <input
                            class="flex-1 rounded border px-2 py-0.5 text-sm"
                            placeholder="note…"
                        />
                    </div>
                </div>

                <div class="rounded border p-3">
                    <p class="mb-1 font-medium text-green-600">
                        ✓ :key="city.id"
                    </p>
                    <!--
                        RIGHT: the id travels with the item wherever it moves.
                        Vue sees "new key 4 at the top, key 1 just moved down", so it CREATES one
                        new row and MOVES the existing rows. Your note stays with Leeds.
                        Rule: key by a stable, unique id. Using the index is only OK for static
                        lists that never reorder, insert or delete.
                    -->
                    <div
                        v-for="city in cities"
                        :key="city.id"
                        class="flex items-center gap-2 py-1"
                    >
                        <span class="w-14">{{ city.name }}</span>
                        <input
                            class="flex-1 rounded border px-2 py-0.5 text-sm"
                            placeholder="note…"
                        />
                    </div>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- 4. computed                                                  -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-4 font-semibold">4. computed</h2>

            <!-- ---------- 4a. Deposit calculator ---------- -->
            <h3 class="mb-2 text-sm font-medium">4a. Deposit calculator</h3>
            <div class="mb-3 grid gap-3 sm:grid-cols-2">
                <label class="grid gap-1 text-sm">
                    House price (£)
                    <!-- .number → keep it a number so the maths works (inputs give strings) -->
                    <input
                        v-model.number="housePrice"
                        type="number"
                        class="rounded border px-2 py-1"
                    />
                </label>
                <label class="grid gap-1 text-sm">
                    Deposit (%)
                    <input
                        v-model.number="depositPercent"
                        type="number"
                        class="rounded border px-2 py-1"
                    />
                </label>
            </div>

            <!--
                Reading a computed in the template: NO parentheses, NO .value.
                It's a ref, not a function. Writing depositAmount() would be an error.
            -->
            <p class="text-sm">
                Deposit (computed):
                <strong>£{{ depositAmount.toLocaleString() }}</strong>
            </p>
            <p class="text-sm">
                Loan needed (computed from a computed):
                <strong>£{{ loanAmount.toLocaleString() }}</strong>
            </p>

            <p class="text-sm">
                Monthly payment:
                <strong>£{{ monthlyPayment.toLocaleString() }}</strong>
            </p>

            <!-- A method needs () to be called, and it runs on EVERY render -->
            <p class="text-sm">
                Deposit (method): £{{ depositMethod().toLocaleString() }}
            </p>

            <!--
                Open the console (F12), then click this.
                Each click: "[method] depositMethod ran" appears again,
                "[computed] depositAmount recalculated" does NOT, because nothing it reads changed.
                Now change the price: BOTH log, because their input changed.
            -->
            <button
                class="mt-2 mb-5 rounded border px-3 py-1"
                @click="renderCount++"
            >
                Re-render ({{ renderCount }})
            </button>

            <!-- ---------- 4b. Search filter ---------- -->
            <h3 class="mb-2 text-sm font-medium">4b. Search filter</h3>
            <!-- Every keystroke updates search → filteredProperties recalculates → list re-renders -->
            <input
                v-model="search"
                class="mb-2 w-full rounded border px-2 py-1"
                placeholder="Search by title or city, e.g. york"
            />

            <!-- .length works because filteredProperties is an array (inside a ref, auto-unwrapped) -->
            <p class="mb-2 text-sm text-muted-foreground">
                {{ filteredProperties.length }} of
                {{ properties.length }} properties
            </p>

            <!--
                Loop over the COMPUTED, not the original array, so the template stays simple.
                This is also the answer to "don't put v-if and v-for on the same element":
                filter in a computed, then v-for over the result.
            -->
            <ul
                v-if="filteredProperties.length"
                class="list-inside list-disc text-sm"
            >
                <li v-for="property in filteredProperties" :key="property.id">
                    {{ property.title }}, {{ property.city }}
                </li>
            </ul>
            <!-- Empty state: shown when the filter matches nothing (length 0 is falsy) -->
            <p v-else class="text-sm text-muted-foreground">
                No properties match "{{ search }}".
            </p>
        </section>

        <!-- ============================================================ -->
        <!-- 5. watch & watchEffect                                       -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">5. watch &amp; watchEffect</h2>
            <p class="mb-4 text-sm text-muted-foreground">
                These react to the inputs in section 4. Keep the console (F12)
                open.
            </p>

            <!-- ---------- 5a. watch: old → new ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                5a. watch(search): type in the 4b search box
            </h3>
            <!-- Show the list only once it has entries (length 0 is falsy) -->
            <ol
                v-if="searchHistory.length"
                class="mb-4 list-inside list-decimal text-sm"
            >
                <!--
                    Keying by index is acceptable HERE: plain text rows with no inputs
                    or component state that could end up on the wrong row.
                -->
                <li v-for="(entry, index) in searchHistory" :key="index">
                    {{ entry }}
                </li>
            </ol>
            <p v-else class="mb-4 text-sm text-muted-foreground">
                No changes yet (watch is lazy).
            </p>

            <!-- ---------- 5b. watch + immediate ---------- -->
            <h3 class="mb-1 text-sm font-medium">
                5b. watch(housePrice, …, { immediate: true }): change the 4a
                price
            </h3>
            <p class="mb-4 text-sm">
                <!-- While the fake request is pending, show the loading text -->
                <span v-if="isCheckingRates" class="text-blue-600"
                    >Checking mortgage rates…</span
                >
                <!-- It already has a value on page load, thanks to immediate: true -->
                <span v-else
                    >Rate: <strong>{{ rateMessage }}</strong></span
                >
            </p>

            <!-- ---------- 5c. watchEffect ---------- -->
            <h3 class="mb-1 text-sm font-medium">5c. watchEffect</h3>
            <p class="text-sm text-muted-foreground">
                Output is in the console: "[watchEffect] search=… → N results".
            </p>
        </section>

        <!-- Next topic sections are added below, one at a time. -->
    </div>
</template>
