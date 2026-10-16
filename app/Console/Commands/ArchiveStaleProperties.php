<?php

namespace App\Console\Commands;

use App\Enums\PropertyStatus;
use App\Models\Property;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Unpublish listings that have been live for a long time and are still "Available".
 * They go back to draft so the agency can check the price and details.
 *
 * Usage:
 *   php artisan properties:archive-stale              (older than 90 days)
 *   php artisan properties:archive-stale --days=30
 *   php artisan properties:archive-stale --dry-run    (only report, change nothing)
 *
 * Scheduled daily in routes/console.php.
 */
#[Signature('properties:archive-stale
    {--days=90 : Unpublish listings published more than this many days ago}
    {--dry-run : List what would change without saving anything}')]
#[Description('Move long-running, still available listings back to draft')]
class ArchiveStaleProperties extends Command
{
    /**
     * A listing with an enquiry in this many days is still "live", however old it is.
     */
    public const RECENT_ENQUIRY_DAYS = 30;

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('--days must be 1 or more.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $query = Property::query()
            ->published()
            ->where('status', PropertyStatus::Available)
            ->where('published_at', '<=', now()->subDays($days))
            // Not stale if people are still asking about it. last_enquiry_at is
            // kept up to date by the RecordLastEnquiryOnProperty listener.
            ->where(fn ($q) => $q
                ->whereNull('last_enquiry_at')
                ->orWhere('last_enquiry_at', '<', now()->subDays(self::RECENT_ENQUIRY_DAYS)));

        $total = $query->count();

        if ($total === 0) {
            $this->info('Nothing to archive.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry run] ' : '')."Found {$total} stale ".str('listing')->plural($total).'.');

        $bar = $this->output->createProgressBar($total);
        $archived = 0;

        // chunkById(200): process 200 rows at a time, so memory stays flat even
        // with millions of rows.
        //
        // Why chunkById and NOT chunk: chunk() pages with LIMIT/OFFSET. This loop
        // sets published_at = null, so those rows stop matching the query; on the
        // next page the OFFSET then skips rows that moved up, and about half are
        // never processed. chunkById() pages with "WHERE id > last id", which is
        // safe when the loop changes the rows it is reading.
        $query->chunkById(200, function (Collection $properties) use ($dryRun, $bar, &$archived): void {
            foreach ($properties as $property) {
                /** @var Property $property */
                if (! $dryRun) {
                    // One save() per model (not one mass update) so observers and
                    // model events still run, e.g. if publishing later sends emails.
                    $property->published_at = null;
                    $property->save();
                }

                $archived++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info($dryRun
            ? "Would archive {$archived} listings. Run without --dry-run to apply."
            : "Archived {$archived} listings.");

        return self::SUCCESS;
    }
}
