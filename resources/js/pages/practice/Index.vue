<!--
    A .vue file is a Single File Component (SFC). It has up to three blocks:
      <script setup>  → the logic (runs once, each time the component is created)
      <template>      → the HTML Vue renders, which can use anything declared in <script setup>
      <style>         → optional CSS (we use Tailwind classes instead)
-->

<!--
    setup    → Composition API shorthand: every top-level variable/function/import
               is automatically usable in the template, with no `return {}` needed.
    lang="ts" → this script is TypeScript, so we get type checking.
-->
<script setup lang="ts">
// Head → Inertia component that sets the browser tab <title> (and <meta> tags).
// Link → Inertia component that renders an <a> but navigates WITHOUT a full page
//        reload: it fetches the next page's props as JSON and swaps the component.
import { Head, Link } from '@inertiajs/vue3';

// route() → Ziggy helper. Turns a Laravel route NAME into a URL,
// e.g. route('practice.binding') → '/practice/binding'. If the URL in
// web.php changes later, the Vue code keeps working because it uses the name.
import { route } from 'ziggy-js';

// defineOptions() → sets component options that <script setup> can't express
// directly. Here we set `layout`, which our app.ts reads.
// This page has no layout rule in app.ts, so it falls to the default
// (AppLayout, with sidebar and header). AppLayout receives `breadcrumbs` as a prop.
defineOptions({
    layout: {
        // Breadcrumb trail shown at the top: "Practice"
        breadcrumbs: [{ title: 'Practice', href: route('practice.index') }],
    },
});

// A plain (non-reactive) array. It never changes at runtime, so it doesn't
// need ref(). Vue only has to track data that changes.
// Add a row here each time a new lesson page is created.
const lessons = [
    {
        day: 1, // shown as "Day 1"
        title: 'Binding & reactivity', // link text
        routeName: 'practice.binding', // Laravel route name, passed to route()
        topics: 'interpolation, ref/reactive, :class/:style, @events, v-model', // subtitle
    },
    {
        day: 1,
        title: 'Lists, computed & watch',
        routeName: 'practice.lists',
        topics: 'v-if/v-show, v-for + :key, computed, watch, watchEffect',
    },
    {
        day: 2,
        title: 'Components',
        routeName: 'practice.components',
        topics: 'props, emits, v-model on components, slots, modals',
    },
];
</script>

<!-- <template> → the markup. Vue compiles it into a render function. -->
<template>
    <!-- Sets the tab title. app.ts appends the app name: "Vue Practice - Laravel" -->
    <Head title="Vue Practice" />

    <!-- Outer wrapper: vertical flex column, gap between children, padding -->
    <div class="flex flex-col gap-4 p-4">
        <!-- Page heading: static text, no binding needed -->
        <h1 class="text-2xl font-semibold">Vue Practice</h1>

        <!-- Grid of lesson cards: 1 column on mobile, 2 from the md breakpoint up -->
        <ul class="grid gap-3 md:grid-cols-2">
            <!--
                v-for="lesson in lessons" → repeat this <li> once for each item in the array.
                                            `lesson` is the current item inside the loop.
                :key="lesson.routeName"   → a unique id for each row, so Vue can track which
                                            <li> is which when the list changes (reorder,
                                            add, remove) instead of re-rendering all of them.
                                            Always use a stable unique value, never the index
                                            if the list can change.
            -->
            <li
                v-for="lesson in lessons"
                :key="lesson.routeName"
                class="rounded-xl border p-4"
            >
                <!--
                    :href="route(...)" → the colon (v-bind) means "evaluate as JavaScript".
                                         Without it, href would be the literal text "route(...)".
                    Clicking this Link does an Inertia visit (XHR) instead of a full reload.
                -->
                <Link
                    :href="route(lesson.routeName)"
                    class="font-medium hover:underline"
                >
                    <!-- {{ }} interpolation: prints the values as text -->
                    Day {{ lesson.day }}: {{ lesson.title }}
                </Link>

                <!-- Smaller grey subtitle listing the lesson's topics -->
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ lesson.topics }}
                </p>
            </li>
        </ul>
    </div>
</template>
