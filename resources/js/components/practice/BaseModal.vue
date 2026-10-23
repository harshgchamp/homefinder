<!--
    CHILD COMPONENT: BaseModal
    A reusable modal (popup dialog) built only from things you already know:
      v-model  → the parent opens/closes it      (Topic 3: defineModel)
      props    → the title                        (Topic 1)
      slots    → the body and footer content      (Topic 4)
    Plus three new pieces:
      <Teleport>   → renders the modal at the end of <body>, outside the page layout
      <Transition> → fade in/out animation
      lifecycle hooks (onMounted / onUnmounted) → listen for the ESC key
    Used in: resources/js/pages/practice/Components.vue

    In real projects, prefer the accessible Dialog in components/ui/dialog (Reka UI):
    it also traps keyboard focus inside the modal and locks page scrolling.
    Building this one teaches what such libraries do for you.
-->
<script setup lang="ts">
// onMounted   → runs once, AFTER the component is added to the page.
// onUnmounted → runs once, when the component is REMOVED from the page.
import { onMounted, onUnmounted } from 'vue';

/*
 * The open/closed state, owned by the PARENT:
 *   <BaseModal v-model="isOpen">
 * Reading open.value → is it open? Writing open.value = false → tells the parent to close it.
 */
const open = defineModel<boolean>({ default: false });

// The heading text. Optional, so the modal works without a title too.
withDefaults(defineProps<{ title?: string }>(), {
    title: '',
});

// Closes the modal by updating the parent's v-model value.
function close() {
    open.value = false;
}

/*
 * The ESC key: a keydown listener on the whole window, because keyboard events
 * only reach elements that have focus, and the modal may not have it.
 * We only react when the modal is open and the key is Escape.
 */
function onKeydown(event: KeyboardEvent) {
    if (open.value && event.key === 'Escape') {
        close();
    }
}

// Start listening once the component is on the page…
onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

/*
 * …and STOP listening when it's removed (e.g. navigating to another Inertia page).
 * Forgetting this is a classic memory leak: the old listener keeps running
 * forever, and each visit adds another one.
 */
onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <!--
        <Teleport to="body"> → Vue renders the content at the END of <body>, not here.
        Why: a modal must sit ON TOP of everything. Inside the page it could be clipped by a
        parent with overflow:hidden, or hidden behind the sidebar (z-index stacking).
        It still behaves like part of THIS component: props, v-model and slots all work.
        Inspect the page while it's open: the modal is at the bottom of <body>.
    -->
    <Teleport to="body">
        <!--
            <Transition> → animates ONE element entering/leaving (v-if or v-show inside).
            The *-class props add Tailwind classes at each step:
              enter-from → start state when appearing (invisible)
              enter-active → while appearing (animate opacity for 150ms)
              leave-to → end state when disappearing (invisible)
        -->
        <Transition
            enter-active-class="transition-opacity duration-150"
            enter-from-class="opacity-0"
            leave-active-class="transition-opacity duration-150"
            leave-to-class="opacity-0"
        >
            <!--
                The BACKDROP: a dark layer covering the whole screen.
                fixed inset-0 → cover the viewport. z-50 → above the rest of the page.
                @click.self → close ONLY when the click lands on the backdrop ITSELF.
                Without .self, clicking INSIDE the white box would also close it, because
                click events bubble up from the box to the backdrop.
            -->
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
                @click.self="close"
            >
                <!--
                    The dialog box.
                    role="dialog" + aria-modal → tells screen readers this is a modal dialog.
                    aria-labelledby → its accessible name comes from the heading with that id.
                -->
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="base-modal-title"
                    class="w-full max-w-md rounded-lg bg-background shadow-lg"
                >
                    <!-- Header: the title prop + a × close button -->
                    <div
                        class="flex items-center justify-between border-b px-4 py-3"
                    >
                        <h2 id="base-modal-title" class="font-semibold">
                            {{ title }}
                        </h2>
                        <button
                            type="button"
                            class="text-xl leading-none text-muted-foreground"
                            aria-label="Close"
                            @click="close"
                        >
                            ×
                        </button>
                    </div>

                    <!-- Body: the parent's DEFAULT slot content -->
                    <div class="px-4 py-3 text-sm">
                        <slot />
                    </div>

                    <!--
                        Footer: the parent's #footer slot. It's a SCOPED slot that hands the
                        parent our close() function, so a "Cancel" button in the parent can
                        close the modal without the parent writing its own close logic.
                    -->
                    <div
                        v-if="$slots.footer"
                        class="flex justify-end gap-2 border-t px-4 py-3"
                    >
                        <slot name="footer" :close="close" />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
