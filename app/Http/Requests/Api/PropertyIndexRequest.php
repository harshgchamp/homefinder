<?php

namespace App\Http\Requests\Api;

use App\Enums\ListingType;
use App\Models\Property;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query string of GET /api/v1/properties. Only the PUBLIC filters: status,
 * publication, agency_id and trashed from the admin list are not accepted,
 * so a client cannot ask for drafts or deleted listings.
 */
class PropertyIndexRequest extends FormRequest
{
    /**
     * Public endpoint: anyone may search.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'listing_type' => ['nullable', Rule::enum(ListingType::class)],
            'property_type_id' => ['nullable', 'integer'],
            'city' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'integer', 'min:0'],
            'max_price' => ['nullable', 'integer', 'min:0', Rule::when($this->filled('min_price'), 'gte:min_price')],
            'min_bedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'features' => ['nullable', 'array', 'max:20'],
            'features.*' => ['integer'],
            'sort' => ['nullable', Rule::in(array_keys(Property::SORTS))],
            // Capped: ?per_page=100000 would load the whole table in one request.
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * The validated filters with the right PHP types, ready for Property::filter().
     *
     * @return array{
     *     search: string|null, listing_type: string|null, property_type_id: int|null,
     *     city: string|null, min_price: int|null, max_price: int|null,
     *     min_bedrooms: int|null, features: list<int>,
     * }
     */
    public function filters(): array
    {
        $int = fn (string $key): ?int => $this->filled($key) ? $this->integer($key) : null;
        $str = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->squish()->value() : null;

        return [
            'search' => $str('search'),
            'listing_type' => $str('listing_type'),
            'property_type_id' => $int('property_type_id'),
            'city' => $str('city'),
            'min_price' => $int('min_price'),
            'max_price' => $int('max_price'),
            'min_bedrooms' => $int('min_bedrooms'),
            'features' => array_values(array_map(fn (mixed $id): int => (int) $id, $this->array('features'))),
        ];
    }

    public function sort(): string
    {
        return $this->filled('sort') ? $this->string('sort')->value() : 'newest';
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
