<?php

namespace App\Http\Resources;

use App\Models\Feature;
use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One shape for a property on every admin page. Fields that a page did not
 * load are simply left out, thanks to the when...() helpers:
 *
 *   whenHas('description')    -> only if the column was SELECTed
 *   whenLoaded('agency')      -> only if the relation was eager loaded (never lazy loads)
 *   whenCounted('enquiries')  -> only if the query used withCount('enquiries')
 *
 * So the list page (few columns, counts) and the detail page (everything)
 * share this class without sending unneeded data or triggering extra queries.
 *
 * @mixin Property
 */
class PropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            // Raw ids for the edit form's select boxes.
            'agency_id' => $this->agency_id,
            'property_type_id' => $this->property_type_id,
            'description' => $this->whenHas('description'),

            // Enums are sent as { value, label }: the value for forms and filters,
            // the label for display, so Vue never has to translate 'under_offer'.
            'listing_type' => ['value' => $this->listing_type->value, 'label' => $this->listing_type->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'publication' => ['value' => $this->publication_status->value, 'label' => $this->publication_status->label()],

            'price' => $this->price,
            'formatted_price' => $this->formatted_price,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'furnished' => $this->whenHas('furnished'),

            'address_line_1' => $this->whenHas('address_line_1'),
            'city' => $this->city,
            'postcode' => $this->postcode,

            'published_at' => $this->published_at?->format('j M Y, H:i'),
            // The format an <input type="datetime-local"> expects.
            'published_at_input' => $this->published_at?->format('Y-m-d\TH:i'),
            // whenHas: the admin list does not select this column.
            'last_enquiry_ago' => $this->whenHas('last_enquiry_at', fn () => $this->last_enquiry_at?->diffForHumans()),
            'created_at' => $this->created_at?->format('j M Y'),
            'updated_ago' => $this->updated_at?->diffForHumans(),
            'deleted_at' => $this->deleted_at?->format('j M Y'),
            'is_trashed' => $this->trashed(),

            'agency' => $this->whenLoaded('agency', fn () => [
                'id' => $this->agency->id,
                'name' => $this->agency->name,
            ]),
            'property_type' => $this->whenLoaded('propertyType', fn () => [
                'id' => $this->propertyType->id,
                'name' => $this->propertyType->name,
            ]),
            'features' => $this->whenLoaded('features', fn () => $this->features->map(fn (Feature $feature): array => [
                'id' => $feature->id,
                'name' => $feature->name,
            ])),
            'images' => PropertyImageResource::collection($this->whenLoaded('images')),
            'main_image_url' => $this->whenLoaded('mainImage', fn () => $this->mainImage?->url),

            'enquiries_count' => $this->whenCounted('enquiries'),
            'favourites_count' => $this->whenCounted('favouritedBy'),
        ];
    }
}
