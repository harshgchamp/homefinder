<?php

namespace App\Observers;

use App\Models\PropertyImage;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * ShouldHandleEventsAfterCommit: when an image is deleted inside DB::transaction(),
 * this observer waits until the transaction COMMITS. If the transaction rolls back,
 * the row comes back, so the file must not have been deleted.
 * Outside a transaction it runs straight away.
 */
class PropertyImageObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * The row is gone, so remove the file it pointed to.
     */
    public function deleted(PropertyImage $image): void
    {
        PropertyImage::disk()->delete($image->path);
    }
}
