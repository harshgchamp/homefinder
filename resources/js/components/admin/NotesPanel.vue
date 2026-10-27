<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fieldClass } from '@/lib/fieldClass';
import type { Note } from '@/types';

/**
 * Private notes on a property or an agency. The panel does not know which:
 * the page passes the notes and the URL to post to. That mirrors the server,
 * where one polymorphic notes table serves both.
 */
const props = defineProps<{
    notes: Note[];
    // e.g. route('admin.properties.notes.store', property.id)
    storeUrl: string;
}>();

const form = useForm({
    body: '',
});

const submit = (): void => {
    form.post(props.storeUrl, {
        // Why: the panel is at the bottom of a long page; stay where the user is.
        preserveScroll: true,
        // Why: clear the textarea only when the note was saved (keep it on a validation error).
        onSuccess: () => form.reset(),
    });
};

const destroy = (note: Note): void => {
    if (confirm('Delete this note?')) {
        router.delete(route('admin.notes.destroy', note.id), {
            preserveScroll: true,
        });
    }
};
</script>

<template>
    <section class="space-y-4 rounded-xl border p-4">
        <h3 class="font-medium">
            Private notes
            <span class="font-normal text-muted-foreground"
                >· only visible in the admin area</span
            >
        </h3>

        <form class="space-y-2" @submit.prevent="submit">
            <Label for="note-body" class="sr-only">New note</Label>
            <textarea
                id="note-body"
                v-model="form.body"
                rows="3"
                required
                maxlength="2000"
                placeholder="e.g. Owner wants a quick sale"
                :class="[fieldClass, 'h-auto py-2']"
                :aria-invalid="!!form.errors.body || undefined"
            />
            <InputError :message="form.errors.body" />
            <!-- Why :disabled: stops a double click from saving the note twice -->
            <Button type="submit" size="sm" :disabled="form.processing"
                >Add note</Button
            >
        </form>

        <p v-if="notes.length === 0" class="text-sm text-muted-foreground">
            No notes yet.
        </p>
        <ul v-else class="space-y-3">
            <li v-for="note in notes" :key="note.id" class="text-sm">
                <div class="flex items-start justify-between gap-2">
                    <p class="font-medium">
                        {{ note.author }}
                        <span
                            class="font-normal text-muted-foreground"
                            :title="note.created_at ?? ''"
                            >· {{ note.created_ago }}</span
                        >
                    </p>
                    <!-- Why v-if: can_delete comes from NotePolicy; the server checks again on delete -->
                    <Button
                        v-if="note.can_delete"
                        variant="ghost"
                        size="sm"
                        class="h-auto px-2 py-0 text-destructive"
                        @click="destroy(note)"
                        >Delete</Button
                    >
                </div>
                <!-- whitespace-pre-line keeps the line breaks the user typed -->
                <p class="whitespace-pre-line text-muted-foreground">
                    {{ note.body }}
                </p>
            </li>
        </ul>
    </section>
</template>
