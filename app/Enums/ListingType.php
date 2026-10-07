<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ListingType: string
{
    use HasOptions;

    case Sale = 'sale';
    case Rent = 'rent';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'For sale',
            self::Rent => 'To rent',
        };
    }
}
