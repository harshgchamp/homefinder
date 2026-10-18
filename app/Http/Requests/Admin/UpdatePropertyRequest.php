<?php

namespace App\Http\Requests\Admin;

use App\Models\Property;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Same rules as creating, plus the ids of existing images to remove.
 */
class UpdatePropertyRequest extends StorePropertyRequest
{
    /**
     * PropertyPolicy::update() for the property in the URL.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->property()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),

            // Override: an active agency, OR the agency the property already has
            // (so editing a property whose agency was deactivated still works).
            // The inner closure adds brackets: "id = ? AND (is_active = 1 OR id = ?)".
            'agency_id' => [
                'required', 'integer',
                Rule::exists('agencies', 'id')->where(fn ($query) => $query->where(
                    fn ($q) => $q->where('is_active', true)->orWhere('id', $this->property()->agency_id),
                )),
                ...$this->ownAgencyOnly(),
            ],

            'remove_images' => ['nullable', 'array'],
            // Only images that belong to THIS property, so a crafted request
            // cannot delete another property's images.
            'remove_images.*' => [
                'integer',
                Rule::exists('property_images', 'id')->where('property_id', $this->property()->id),
            ],
        ];
    }

    /**
     * Parent checks, plus: existing - removed + new must stay within the limit.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),

            function (Validator $validator): void {
                $total = $this->property()->images()->count()
                    - count($this->array('remove_images'))
                    // Arr::wrap(): file() can return one file or an array of files.
                    + count(Arr::wrap($this->file('images', [])));

                if ($total > self::MAX_IMAGES) {
                    $validator->errors()->add(
                        'images',
                        'A property can have at most '.self::MAX_IMAGES." images (this would make {$total}).",
                    );
                }
            },
        ];
    }

    /**
     * The property from the URL, already loaded by route model binding.
     */
    private function property(): Property
    {
        /** @var Property */
        return $this->route('property');
    }
}
