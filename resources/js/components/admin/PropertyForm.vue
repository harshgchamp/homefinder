<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { route } from 'ziggy-js';
import ImageUploader from '@/components/admin/ImageUploader.vue';
import SelectField from '@/components/admin/SelectField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { fieldClass } from '@/lib/fieldClass';
import type { Property, PropertyFormOptions } from '@/types';

/**
 * One form for both pages: Create passes no property, Edit passes one.
 */
const props = defineProps<{
    property?: Property;
    options: PropertyFormOptions;
}>();

const MAX_IMAGES = 10; // same as StorePropertyRequest::MAX_IMAGES

// Why useForm (and not <Form> like the features pages): this form needs
// computed options, a watcher, file arrays and an upload progress bar,
// which are easier with the form data in a reactive object.
const form = useForm({
    agency_id: props.property?.agency_id ?? null,
    property_type_id: props.property?.property_type_id ?? null,
    title: props.property?.title ?? '',
    description: props.property?.description ?? '',
    listing_type: props.property?.listing_type.value ?? 'sale',
    status: props.property?.status.value ?? 'available',
    price: props.property?.price ?? null,
    bedrooms: props.property?.bedrooms ?? null,
    bathrooms: props.property?.bathrooms ?? 1,
    // Why a string: a select with three choices (yes / no / not specified).
    furnished:
        props.property?.furnished === true
            ? '1'
            : props.property?.furnished === false
              ? '0'
              : '',
    address_line_1: props.property?.address_line_1 ?? '',
    city: props.property?.city ?? '',
    postcode: props.property?.postcode ?? '',
    published_at: props.property?.published_at_input ?? '',
    features: props.property?.features?.map((f) => f.id) ?? [],
    images: [] as File[],
    remove_images: [] as number[],
});

// --- Status depends on listing type --------------------------------------------
// Same rule as PropertyStatus::isAllowedFor() on the server. The server still
// validates it; this only stops the user picking an option that would fail.

const statusOptions = computed(() => {
    const allowed =
        props.options.statusesByListingType[form.listing_type] ?? [];

    return props.options.statuses.filter((s) => allowed.includes(s.value));
});

watch(
    () => form.listing_type,
    () => {
        if (!statusOptions.value.some((s) => s.value === form.status)) {
            form.status = 'available';
        }
    },
);

// --- Image events from ImageUploader ---------------------------------------------

const addImages = (files: File[]): void => {
    form.images = [...form.images, ...files];
};

const removeNewImage = (index: number): void => {
    form.images = form.images.filter((_, i) => i !== index);
};

const toggleExistingImage = (id: number): void => {
    form.remove_images = form.remove_images.includes(id)
        ? form.remove_images.filter((x) => x !== id)
        : [...form.remove_images, id];
};

// Why: errors for files come back as "images.0", "images.1"... Show the first one.
const imageError = computed(
    () =>
        Object.entries(form.errors as Record<string, string>).find(([key]) =>
            key.startsWith('images'),
        )?.[1],
);

// --- Submit ----------------------------------------------------------------------

const submit = (): void => {
    // Why forceFormData: files can only be sent as multipart/form-data, not JSON.
    const options = { forceFormData: true, preserveScroll: true };

    if (props.property) {
        // Why POST + _method: PHP only reads uploaded files on POST requests.
        // Laravel sees _method=put and routes it to update() (method spoofing).
        form.transform((data) => ({ ...data, _method: 'put' })).post(
            route('admin.properties.update', props.property.id),
            options,
        );
    } else {
        form.post(route('admin.properties.store'), options);
    }
};
</script>

<template>
    <form class="max-w-3xl space-y-8" @submit.prevent="submit">
        <!-- Basics -->
        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="mb-2 text-base font-medium">Basics</legend>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="title">Title</Label>
                <input
                    id="title"
                    v-model="form.title"
                    required
                    maxlength="255"
                    placeholder="e.g. 3 bedroom semi-detached house"
                    :class="fieldClass"
                    :aria-invalid="!!form.errors.title || undefined"
                />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <Label for="agency_id">Agency</Label>
                <SelectField
                    id="agency_id"
                    v-model="form.agency_id"
                    :options="options.agencies"
                    placeholder="Choose an agency"
                    :invalid="!!form.errors.agency_id"
                />
                <InputError :message="form.errors.agency_id" />
            </div>

            <div class="grid gap-2">
                <Label for="property_type_id">Type</Label>
                <SelectField
                    id="property_type_id"
                    v-model="form.property_type_id"
                    :options="options.types"
                    placeholder="Choose a type"
                    :invalid="!!form.errors.property_type_id"
                />
                <InputError :message="form.errors.property_type_id" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="description">Description</Label>
                <textarea
                    id="description"
                    v-model="form.description"
                    rows="6"
                    required
                    maxlength="5000"
                    :class="[fieldClass, 'h-auto py-2']"
                    :aria-invalid="!!form.errors.description || undefined"
                />
                <InputError :message="form.errors.description" />
            </div>
        </fieldset>

        <!-- Listing -->
        <fieldset class="grid gap-4 sm:grid-cols-3">
            <legend class="mb-2 text-base font-medium">Listing</legend>

            <div class="grid gap-2">
                <Label for="listing_type">For</Label>
                <SelectField
                    id="listing_type"
                    v-model="form.listing_type"
                    :options="options.listingTypes"
                />
                <InputError :message="form.errors.listing_type" />
            </div>

            <div class="grid gap-2">
                <Label for="status">Status</Label>
                <!-- Only the statuses allowed for the chosen listing type -->
                <SelectField
                    id="status"
                    v-model="form.status"
                    :options="statusOptions"
                    :invalid="!!form.errors.status"
                />
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2">
                <Label for="price">
                    Price (£{{
                        form.listing_type === 'rent' ? ' per month' : ''
                    }})
                </Label>
                <input
                    id="price"
                    v-model.number="form.price"
                    type="number"
                    min="1"
                    required
                    :class="fieldClass"
                    :aria-invalid="!!form.errors.price || undefined"
                />
                <InputError :message="form.errors.price" />
            </div>

            <div class="grid gap-2">
                <Label for="bedrooms">Bedrooms</Label>
                <input
                    id="bedrooms"
                    v-model.number="form.bedrooms"
                    type="number"
                    min="0"
                    max="20"
                    required
                    :class="fieldClass"
                />
                <InputError :message="form.errors.bedrooms" />
            </div>

            <div class="grid gap-2">
                <Label for="bathrooms">Bathrooms</Label>
                <input
                    id="bathrooms"
                    v-model.number="form.bathrooms"
                    type="number"
                    min="0"
                    max="20"
                    required
                    :class="fieldClass"
                />
                <InputError :message="form.errors.bathrooms" />
            </div>

            <!-- Furnished only means something for rentals (the server clears it for sales) -->
            <div v-if="form.listing_type === 'rent'" class="grid gap-2">
                <Label for="furnished">Furnished</Label>
                <select
                    id="furnished"
                    v-model="form.furnished"
                    :class="fieldClass"
                >
                    <option value="">Not specified</option>
                    <option value="1">Furnished</option>
                    <option value="0">Unfurnished</option>
                </select>
                <InputError :message="form.errors.furnished" />
            </div>
        </fieldset>

        <!-- Address -->
        <fieldset class="grid gap-4 sm:grid-cols-3">
            <legend class="mb-2 text-base font-medium">Address</legend>

            <div class="grid gap-2 sm:col-span-3">
                <Label for="address_line_1">Address</Label>
                <input
                    id="address_line_1"
                    v-model="form.address_line_1"
                    required
                    :class="fieldClass"
                />
                <InputError :message="form.errors.address_line_1" />
            </div>

            <div class="grid gap-2 sm:col-span-2">
                <Label for="city">City</Label>
                <input
                    id="city"
                    v-model="form.city"
                    required
                    :class="fieldClass"
                />
                <InputError :message="form.errors.city" />
            </div>

            <div class="grid gap-2">
                <Label for="postcode">Postcode</Label>
                <!-- The model's mutator tidies it: "rm70ab" is saved as "RM7 0AB" -->
                <input
                    id="postcode"
                    v-model="form.postcode"
                    required
                    maxlength="10"
                    :class="fieldClass"
                />
                <InputError :message="form.errors.postcode" />
            </div>
        </fieldset>

        <!-- Features -->
        <fieldset class="space-y-2">
            <legend class="mb-2 text-base font-medium">Features</legend>
            <div class="flex flex-wrap gap-x-5 gap-y-2">
                <label
                    v-for="feature in options.features"
                    :key="feature.value"
                    class="flex items-center gap-2 text-sm"
                >
                    <!-- v-model on an array: saved with features()->sync() on the server -->
                    <input
                        v-model="form.features"
                        type="checkbox"
                        :value="feature.value"
                    />
                    {{ feature.label }}
                </label>
            </div>
            <InputError :message="form.errors.features" />
        </fieldset>

        <!-- Images -->
        <fieldset class="space-y-2">
            <legend class="mb-2 text-base font-medium">Images</legend>
            <ImageUploader
                :existing="property?.images ?? []"
                :removed-ids="form.remove_images"
                :files="form.images"
                :max="MAX_IMAGES"
                @add="addImages"
                @remove-new="removeNewImage"
                @toggle-existing="toggleExistingImage"
            />
            <InputError :message="imageError" />
        </fieldset>

        <!-- Publishing -->
        <fieldset class="grid gap-2 sm:max-w-xs">
            <legend class="mb-2 text-base font-medium">Publishing</legend>
            <Label for="published_at">Publish date</Label>
            <input
                id="published_at"
                v-model="form.published_at"
                type="datetime-local"
                :class="fieldClass"
            />
            <p class="text-sm text-muted-foreground">
                Empty = draft. A future date = scheduled.
            </p>
            <InputError :message="form.errors.published_at" />
        </fieldset>

        <!-- Upload progress: form.progress is set while files are uploading -->
        <progress
            v-if="form.progress"
            :value="form.progress.percentage"
            max="100"
            class="w-full"
        >
            {{ form.progress.percentage }}%
        </progress>

        <div class="flex items-center gap-3">
            <!-- :disabled stops a double click from saving twice -->
            <Button type="submit" :disabled="form.processing">
                {{ property ? 'Save changes' : 'Create property' }}
            </Button>
            <Button variant="ghost" as-child>
                <Link
                    :href="
                        property
                            ? route('admin.properties.show', property.id)
                            : route('admin.properties.index')
                    "
                    >Cancel</Link
                >
            </Button>
        </div>
    </form>
</template>
