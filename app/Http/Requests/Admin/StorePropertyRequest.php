<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Rules\UkPostcode;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Validator;

class StorePropertyRequest extends FormRequest
{
    /**
     * The most images one property may have.
     */
    public const MAX_IMAGES = 10;

    /**
     * PropertyPolicy::create(): admins and agents.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Property::class) ?? false;
    }

    /**
     * Runs BEFORE the rules. "Furnished" only means something for rentals,
     * so it is cleared for a sale whatever the form sent.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('listing_type') === ListingType::Sale->value) {
            $this->merge(['furnished' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // exists: the id must be a real row, otherwise the foreign key would
            // fail with a database error instead of a friendly message.
            'agency_id' => ['required', 'integer', Rule::exists('agencies', 'id')->where('is_active', true), ...$this->ownAgencyOnly()],
            'property_type_id' => ['required', 'integer', Rule::exists('property_types', 'id')],

            'title' => ['required', 'string', 'min:5', 'max:255'],
            'description' => ['required', 'string', 'min:20', 'max:5000'],

            // Rule::enum: only the values of the PHP enum are accepted.
            'listing_type' => ['required', Rule::enum(ListingType::class)],
            'status' => ['required', Rule::enum(PropertyStatus::class)],

            // Limits match the column types in the migration (unsigned int / tinyint).
            'price' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'bedrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'bathrooms' => ['required', 'integer', 'min:0', 'max:20'],
            'furnished' => ['nullable', 'boolean'],

            'address_line_1' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'postcode' => ['required', 'string', 'max:10', new UkPostcode],

            // Empty = draft, past = published, future = scheduled.
            'published_at' => ['nullable', 'date'],

            'features' => ['nullable', 'array'],
            // distinct: the same feature id twice in one request is rejected.
            'features.*' => ['integer', 'distinct', Rule::exists('features', 'id')],

            'images' => ['nullable', 'array', 'max:'.self::MAX_IMAGES],
            // max is in kilobytes: 4096 KB = 4 MB. "image" + "mimes" checks the
            // real file content, not only the extension in the file name.
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * Agents may only list properties for the agency they work for.
     * The policy says "agents may create"; this says "...but only for themselves".
     *
     * @return list<In>
     */
    protected function ownAgencyOnly(): array
    {
        $user = $this->user();

        return $user !== null && $user->isAgent() ? [Rule::in([$user->agency_id])] : [];
    }

    /**
     * Checks that need more than one field. These run after the rules above.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // Skip if listing_type or status already failed: the enums below would be null.
                if ($validator->errors()->hasAny(['listing_type', 'status'])) {
                    return;
                }

                $listingType = ListingType::from($this->string('listing_type')->value());
                $status = PropertyStatus::from($this->string('status')->value());

                // The business rule lives in the enum, so it is the same everywhere.
                if (! $status->isAllowedFor($listingType)) {
                    $validator->errors()->add(
                        'status',
                        "\"{$status->label()}\" cannot be used for a property that is {$listingType->label()}.",
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'agency_id.exists' => 'Choose an active agency.',
            'images.max' => 'A property can have at most '.self::MAX_IMAGES.' images.',
            'images.*.max' => 'Each image must be 4 MB or smaller.',
            'images.*.image' => 'Only image files can be uploaded.',
        ];
    }

    /**
     * Friendlier field names in messages: "The type field..." not "The property type id field...".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'agency_id' => 'agency',
            'property_type_id' => 'type',
            'address_line_1' => 'address',
            'published_at' => 'publish date',
            'images.*' => 'image',
        ];
    }
}
