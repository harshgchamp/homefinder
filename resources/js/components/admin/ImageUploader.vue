<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import type { PropertyImage } from '@/types';

const props = defineProps<{
    /** Images already saved (edit page only). */
    existing: PropertyImage[];
    /** Ids of saved images the user ticked for removal. */
    removedIds: number[];
    /** New files picked but not uploaded yet. */
    files: File[];
    max: number;
}>();

// Why defineEmits: this component does not change its props. It tells the
// parent (PropertyForm) what happened, and the parent updates its form data.
// Data flows down as props, changes flow up as events.
const emit = defineEmits<{
    add: [files: File[]];
    removeNew: [index: number];
    toggleExisting: [id: number];
}>();

const input = ref<HTMLInputElement | null>(null);

// Why: how many more images can be picked, counting saved ones that stay.
const remaining = computed(
    () =>
        props.max -
        (props.existing.length - props.removedIds.length) -
        props.files.length,
);

const onPick = (event: Event): void => {
    const picked = Array.from((event.target as HTMLInputElement).files ?? []);

    emit('add', picked.slice(0, Math.max(remaining.value, 0)));

    // Why: clear the input so picking the same file again still fires "change".
    (event.target as HTMLInputElement).value = '';
};

// --- Previews ----------------------------------------------------------------
// URL.createObjectURL() gives a temporary "blob:" URL so a picked file can be
// shown before it is uploaded. Each URL holds the file in memory until it is
// revoked, so old URLs are released whenever the list changes and on unmount.

const previews = ref<string[]>([]);

const revokeAll = (): void => {
    previews.value.forEach((url) => URL.revokeObjectURL(url));
};

watch(
    () => props.files,
    (files) => {
        revokeAll();
        previews.value = files.map((file) => URL.createObjectURL(file));
    },
    { immediate: true },
);

onBeforeUnmount(revokeAll);
</script>

<template>
    <div class="space-y-3">
        <div
            v-if="existing.length || files.length"
            class="grid grid-cols-3 gap-3 sm:grid-cols-5"
        >
            <!-- Saved images: clicking toggles "remove on save" -->
            <button
                v-for="image in existing"
                :key="`saved-${image.id}`"
                type="button"
                class="group relative aspect-square overflow-hidden rounded-md border bg-muted"
                :aria-pressed="removedIds.includes(image.id)"
                :title="
                    removedIds.includes(image.id)
                        ? 'Will be removed. Click to keep.'
                        : 'Click to remove'
                "
                @click="emit('toggleExisting', image.id)"
            >
                <img
                    :src="image.url"
                    alt=""
                    class="size-full object-cover"
                    :class="{ 'opacity-30': removedIds.includes(image.id) }"
                />
                <span
                    v-if="removedIds.includes(image.id)"
                    class="absolute inset-0 grid place-items-center text-xs font-medium text-destructive"
                    >Removed</span
                >
            </button>

            <!-- New files: preview from a blob URL -->
            <div
                v-for="(url, index) in previews"
                :key="url"
                class="relative aspect-square overflow-hidden rounded-md border-2 border-dashed border-primary/40"
            >
                <img :src="url" alt="" class="size-full object-cover" />
                <button
                    type="button"
                    class="absolute top-1 right-1 rounded bg-background/80 px-1.5 text-xs"
                    :aria-label="`Remove ${files[index]?.name}`"
                    @click="emit('removeNew', index)"
                >
                    ✕
                </button>
            </div>
        </div>

        <input
            ref="input"
            type="file"
            accept="image/jpeg,image/png,image/webp"
            multiple
            class="hidden"
            @change="onPick"
        />
        <div class="flex items-center gap-3">
            <Button
                type="button"
                variant="outline"
                :disabled="remaining <= 0"
                @click="input?.click()"
            >
                Add images
            </Button>
            <span class="text-sm text-muted-foreground">
                JPG, PNG or WebP, up to 4 MB each. {{ Math.max(remaining, 0) }}
                more allowed.
            </span>
        </div>
    </div>
</template>
