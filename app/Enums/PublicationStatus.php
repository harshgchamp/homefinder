<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Where a listing is in its publishing life cycle. Not stored in the database:
 * it is worked out from published_at (see Property::publicationStatus()).
 *
 *   published_at IS NULL   -> Draft
 *   published_at >  now()  -> Scheduled
 *   published_at <= now()  -> Published
 */
enum PublicationStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }
}
