<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PropertyStatus: string
{
    use HasOptions;

    case Available = 'available';
    case UnderOffer = 'under_offer';
    case Sold = 'sold';
    case Let = 'let';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::UnderOffer => 'Under offer',
            self::Sold => 'Sold STC',
            self::Let => 'Let agreed',
        };
    }

    /**
     * Some statuses only make sense for a certain listing type. For example,
     * "sold" is only valid for sales, not rentals. Used by the property form
     * (to offer only valid options) and by StorePropertyRequest::after().
     */
    public function isAllowedFor(ListingType $listingType): bool
    {
        return match ($this) {
            self::Available => true,
            self::UnderOffer, self::Sold => $listingType === ListingType::Sale,
            self::Let => $listingType === ListingType::Rent,
        };
    }

    /**
     * @return list<self>
     */
    public static function forListingType(ListingType $listingType): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $status): bool => $status->isAllowedFor($listingType),
        ));
    }
}
