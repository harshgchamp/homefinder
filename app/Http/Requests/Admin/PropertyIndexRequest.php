<?php

namespace App\Http\Requests\Admin;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string of the property list and the CSV export
 * (?search=...&min_price=...). Query strings are user input too: without this,
 * ?sort=password or ?min_price=abc would reach the database query.
 */
class PropertyIndexRequest extends FormRequest
{
    /**
     * PropertyPolicy::viewAny(). Passing the CLASS (no model exists yet) tells
     * Laravel which policy to use.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Property::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'status' => ['nullable', Rule::enum(PropertyStatus::class)],
            'publication' => ['nullable', Rule::enum(PublicationStatus::class)],
            'agency_id' => ['nullable', 'integer'],
            'property_type_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            // gte:min_price compares with another field, but it FAILS when min_price
            // is missing. Rule::when() adds it only if a minimum was actually given,
            // so "max price only" still works.
            'max_price' => ['nullable', 'integer', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['integer'],
            'trashed' => ['nullable', Rule::in(['with', 'only'])],
            'sort' => ['nullable', Rule::in(array_keys(Property::SORTS))],
        ];
    }

    /**
     * A bad filter in the URL sends the user to the unfiltered list (with the
     * error message), instead of back() which could loop on the same bad URL.
     */
    protected function getRedirectUrl(): string
    {
        return route('admin.properties.index');
    }

    /**
     * The validated filters with the right PHP types, ready for Property::filter().
     * Query string values always arrive as strings, so numbers are cast here.
     *
     * @return array{
     *     search: string|null, listing_type: string|null, status: string|null,
     *     publication: string|null, agency_id: int|null, property_type_id: int|null,
     *     city: string|null, min_price: int|null, max_price: int|null,
     *     min_bedrooms: int|null, features: list<int>, trashed: string|null, sort: string,
     * }
     */
    public function filters(): array
    {
        $int = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;
        $str = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->squish()->value() : null;

        return [
            'search' => $str('search'),
            'listing_type' => $str('listing_type'),
            'status' => $str('status'),
            'publication' => $str('publication'),
            'agency_id' => $int('agency_id'),
            'property_type_id' => $int('property_type_id'),
            'city' => $str('city'),
            'min_price' => $int('min_price'),
            'max_price' => $int('max_price'),
            'min_bedrooms' => $int('min_bedrooms'),
            // Cast every id to int; array_values() re-indexes 0,1,2... (a "list").
            'features' => array_values(array_map(fn (mixed $id): int => (int) $id, $this->array('features'))),
            'trashed' => $str('trashed'),
            'sort' => $str('sort') ?? 'newest',
        ];
    }
}
