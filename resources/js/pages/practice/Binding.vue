<!--
    LESSON 1: Binding & reactivity
    Read the <script> first (the data and functions), then the <template>
    (how that data is shown and changed). Each numbered section in the
    template matches a numbered idea in the script.
-->
<script setup lang="ts">
// Head → sets the browser tab title. Link → SPA-style navigation, no full reload.
import { Head, Link } from '@inertiajs/vue3';

// ref      → makes a single value reactive (any type).
// reactive → makes a whole object reactive.
// "Reactive" means: when the value changes, Vue re-renders only the parts of
// the template that use it, automatically. No manual DOM updates.
import { reactive, ref } from 'vue';

// Ziggy: route name → URL.
import { route } from 'ziggy-js';

// Layout options read by AppLayout: breadcrumb trail "Practice > Binding".
defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Practice', href: route('practice.index') }, // first crumb, links back to the list
            { title: 'Binding', href: route('practice.binding') }, // current page
        ],
    },
});

/*
 * 1. ref(): wraps ANY value (string, number, boolean, object).
 *    In <script> you read/write it with .value.
 *    In <template> Vue unwraps it automatically: no .value.
 */

// Reactive string. TypeScript infers the type Ref<string>.
// Bound to an input below, so typing changes it everywhere at once.
const title = ref('Modern 2-bed flat');

// Reactive number. Used in interpolation and in a number input.
const price = ref(250000);

// Reactive boolean. Controls classes, styles and the disabled button in section 2.
const isFeatured = ref(false);

/*
 * 2. reactive(): makes an OBJECT deeply reactive. No .value needed,
 *    but you can't reassign the whole object or destructure it
 *    without losing reactivity. Many teams just use ref() everywhere.
 */

// One object holding all the form fields from section 4.
// Access it as filters.city (no .value) in both script and template.
const filters = reactive({
    city: '', // text input with .trim
    bedrooms: 2, // <select>, default 2
    type: 'flat', // not bound yet: use it in an exercise
    hasGarden: false, // checkbox → true/false
    tenure: 'freehold', // radio group → 'freehold' | 'leasehold'
    notes: '', // textarea with .lazy
});

// 3. Event handlers are plain functions.

// Called by @click on the "Toggle featured" button.
function toggleFeatured() {
    // Flip the boolean. In <script> we MUST use .value to reach a ref's value.
    // Changing it re-renders everything that reads isFeatured.
    isFeatured.value = !isFeatured.value;
}

// Click counter, increased inline in the template with clicks++.
const clicks = ref(0);

// Stores the name of the last key pressed in the input ('a', 'Enter', 'Shift'…).
const lastKey = ref('');

// Called on every keyup in the input. Vue passes the native DOM event
// automatically when you write @keyup="onKeyup" (handler name, no parentheses).
function onKeyup(event: KeyboardEvent) {
    // event.key is the standard browser property naming the key.
    lastKey.value = event.key;
}

// Holds the submitted form as a JSON string. Starts as null (nothing submitted yet).
// ref<string | null> gives TypeScript the type explicitly, because from the null
// alone it couldn't tell the value will later be a string.
const submitted = ref<string | null>(null);

// Called by @submit.prevent on the <form>.
function submit() {
    // JSON.stringify(obj, null, 2) → pretty-printed JSON with 2-space indentation.
    // This takes a snapshot at submit time; later edits won't change it.
    // In a real Inertia app this is where you'd send the data to Laravel (Day 3: useForm).
    submitted.value = JSON.stringify(filters, null, 2);
}
</script>

<template>
    <!-- Browser tab: "Binding - <app name>" -->
    <Head title="Binding" />

    <!-- Page wrapper: column layout, limited width, space between sections -->
    <div class="flex max-w-3xl flex-col gap-8 p-4">
        <!-- Back link to the lesson list. :href evaluates route() as JS. -->
        <Link
            :href="route('practice.index')"
            class="text-sm text-muted-foreground hover:underline"
        >
            <!-- &larr; is the HTML entity for the ← arrow -->
            &larr; All lessons
        </Link>

        <!-- ============================================================ -->
        <!-- 1. Interpolation: {{ }} renders text and accepts any JS expression -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">1. Interpolation</h2>

            <!-- Prints the ref's value. No .value in the template: Vue unwraps refs. -->
            <p>Title: {{ title }}</p>

            <!-- Any JS EXPRESSION works (method calls, maths, ternaries).
                 Statements don't (if, for, let). The output is HTML-escaped, so it's XSS-safe. -->
            <p>Uppercase: {{ title.toUpperCase() }}</p>

            <!-- toLocaleString() adds thousands separators: 250000 → 250,000 -->
            <p>Price: £{{ price.toLocaleString() }}</p>

            <!-- Ternary: condition ? valueIfTrue : valueIfFalse -->
            <p>Status: {{ isFeatured ? 'Featured' : 'Standard' }}</p>
        </section>

        <!-- ============================================================ -->
        <!-- 2. v-bind (:) binds an ATTRIBUTE to JS, not to a plain string -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">
                2. Attribute, class &amp; style binding
            </h2>

            <!--
                OBJECT syntax: { 'class names': condition }
                The classes are ADDED when the condition is truthy and REMOVED when it's falsy.
                The static class="rounded p-3" is always kept; Vue merges the two.
            -->
            <div
                class="rounded p-3"
                :class="{
                    'bg-yellow-100 font-bold dark:bg-yellow-900': isFeatured,
                }"
            >
                Object class syntax
            </div>

            <!--
                ARRAY syntax: [ 'always-on classes', condition ? 'a' : 'b' ]
                Every array item becomes a class. Useful for choosing between two sets.
            -->
            <div
                class="mt-2"
                :class="[
                    'rounded p-3',
                    isFeatured ? 'border-2 border-yellow-500' : 'border',
                ]"
            >
                Array class syntax
            </div>

            <!--
                :style takes an object of CSS properties.
                Keys are camelCase (fontSize, not font-size). Values are strings with units.
            -->
            <p
                class="mt-2"
                :style="{ fontSize: isFeatured ? '1.25rem' : '1rem' }"
            >
                Font size via :style
            </p>

            <!--
                Boolean attribute: when :disabled is true Vue adds `disabled`, and when
                false it removes the attribute completely (it doesn't write disabled="false").
                disabled:opacity-50 is a Tailwind class that fades the button while disabled.
            -->
            <button
                class="mt-2 rounded border px-3 py-1 disabled:opacity-50"
                :disabled="!isFeatured"
            >
                Only enabled when featured
            </button>
        </section>

        <!-- ============================================================ -->
        <!-- 3. v-on (@) listens to DOM events                            -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">3. Events &amp; modifiers</h2>

            <!-- flex-wrap: buttons wrap onto the next line on narrow screens -->
            <div class="flex flex-wrap gap-2">
                <!-- METHOD handler: @click="fnName" calls the function on each click.
                     This flips isFeatured, so sections 1 and 2 update instantly. -->
                <button
                    class="rounded border px-3 py-1"
                    @click="toggleFeatured"
                >
                    Toggle featured (method)
                </button>

                <!-- INLINE handler: a short JS statement right in the template.
                     clicks++ works without .value because the template unwraps refs. -->
                <button class="rounded border px-3 py-1" @click="clicks++">
                    Clicked {{ clicks }} times (inline)
                </button>
            </div>

            <!--
                Two listeners on the same event:
                @keyup="onKeyup"        → runs on EVERY key release; Vue passes the event object.
                @keyup.enter="clicks=0" → KEY MODIFIER: runs only when the key is Enter.
                Other key modifiers: .esc .tab .space .up .down .ctrl .shift
            -->
            <input
                class="mt-3 w-full rounded border px-2 py-1"
                placeholder="Type and watch the last key…"
                @keyup="onKeyup"
                @keyup.enter="clicks = 0"
            />

            <!-- || '–' shows a dash while lastKey is still an empty string (falsy) -->
            <p class="mt-1 text-sm">
                Last key: {{ lastKey || '-' }} (Enter resets the counter)
            </p>
        </section>

        <!-- ============================================================ -->
        <!-- 4. v-model = :value + @input in one, two-way binding         -->
        <!-- ============================================================ -->
        <section class="rounded-xl border p-4">
            <h2 class="mb-2 font-semibold">4. v-model on every input type</h2>

            <!--
                @submit.prevent="submit"
                .prevent is an EVENT MODIFIER: it calls event.preventDefault(), which stops
                the browser's default behaviour of reloading the page on form submit.
                Then submit() runs. Other event modifiers: .stop .once .self
            -->
            <form class="grid gap-3" @submit.prevent="submit">
                <!-- Wrapping an input in <label> makes clicking the text focus the input -->
                <label class="grid gap-1">
                    Title (text)
                    <!--
                        v-model="title" is shorthand for
                        :value="title" @input="title = $event.target.value"
                        Typing updates title, and changing title updates the input.
                        Watch section 1 change as you type.
                    -->
                    <input v-model="title" class="rounded border px-2 py-1" />
                </label>

                <label class="grid gap-1">
                    Price (.number casts the string to a number)
                    <!--
                        HTML inputs ALWAYS give strings, even type="number".
                        .number converts the value with parseFloat, so price stays a number
                        and price.toLocaleString() keeps working.
                    -->
                    <input
                        v-model.number="price"
                        type="number"
                        class="rounded border px-2 py-1"
                    />
                </label>

                <label class="grid gap-1">
                    City (.trim strips whitespace)
                    <!-- .trim removes leading/trailing spaces: "  Leeds " → "Leeds".
                         Bound to a property of the reactive object, so no .value is involved. -->
                    <input
                        v-model="filters.city"
                        class="rounded border px-2 py-1"
                    />
                </label>

                <label class="grid gap-1">
                    Bedrooms (select)
                    <!-- With a <select>, v-model holds the chosen <option>'s value. -->
                    <select
                        v-model.number="filters.bedrooms"
                        class="rounded border px-2 py-1"
                    >
                        <!--
                            v-for="n in 5" → a number range: n = 1, 2, 3, 4, 5 (starts at 1, not 0).
                            :value="n" → the option's value is the NUMBER n.
                                         Without the colon it would be the literal string "n".
                        -->
                        <option v-for="n in 5" :key="n" :value="n">
                            {{ n }}
                        </option>
                    </select>
                </label>

                <!-- fieldset + legend: semantic, accessible grouping for radio buttons -->
                <fieldset class="flex gap-4">
                    <legend>Tenure (radio)</legend>
                    <!--
                        Both radios share v-model="filters.tenure".
                        The checked one writes ITS value into filters.tenure.
                        The radio whose value matches filters.tenure is shown as checked.
                    -->
                    <label
                        ><input
                            v-model="filters.tenure"
                            type="radio"
                            value="freehold"
                        />
                        Freehold</label
                    >
                    <label
                        ><input
                            v-model="filters.tenure"
                            type="radio"
                            value="leasehold"
                        />
                        Leasehold</label
                    >
                </fieldset>

                <label class="flex items-center gap-2">
                    <!-- A single checkbox with v-model → a boolean (true when checked).
                         Exercise 3: several checkboxes on one ARRAY → an array of checked values. -->
                    <input v-model="filters.hasGarden" type="checkbox" /> Has
                    garden (checkbox → boolean)
                </label>

                <label class="grid gap-1">
                    Notes (.lazy updates on change, not every keystroke)
                    <!--
                        Default v-model listens to the `input` event (every keystroke).
                        .lazy listens to `change` instead, which fires on blur (clicking away)
                        or Enter. Watch "Live state" below: it updates only after you leave the box.
                    -->
                    <textarea
                        v-model.lazy="filters.notes"
                        class="rounded border px-2 py-1"
                    />
                </label>

                <!-- type="submit" → clicking it (or pressing Enter in a text input)
                     fires the form's submit event, which @submit.prevent handles. -->
                <button
                    type="submit"
                    class="w-fit rounded bg-primary px-4 py-1 text-primary-foreground"
                >
                    Submit
                </button>
            </form>

            <!--
                {{ filters }} → printing an object in interpolation shows it as JSON.
                It updates live, which is a handy debugging trick for checking reactive state.
                <pre> keeps the whitespace/indentation.
            -->
            <pre class="mt-3 rounded bg-muted p-3 text-xs">
Live state: {{ filters }}</pre>

            <!--
                v-if="submitted" → this <pre> exists in the DOM ONLY when submitted is truthy.
                While it's null (before the first submit), Vue doesn't render it at all.
                (Lesson 2 compares v-if with v-show.)
            -->
            <pre v-if="submitted" class="mt-2 rounded bg-muted p-3 text-xs">
Submitted: {{ submitted }}</pre>
        </section>
    </div>
</template>
