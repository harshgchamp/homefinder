<?php

namespace App\Jobs;

use App\Models\PropertyImage;
use GdImage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Shrinks an uploaded property photo in the background:
 *   - resizes so the longest side is at most 1600px (phone photos are often 4000px+)
 *   - converts to WebP (typically 70-90% smaller than the original JPEG)
 *   - drops all EXIF data, including GPS coordinates of where the photo was taken,
 *     because re-encoding writes only the pixels
 *
 * Why a queued job: decoding and re-encoding a 10 MB photo takes about a second
 * or more. Ten photos would make the admin's "Save" wait 10+ seconds. Queued, the
 * request returns immediately and a worker process does this afterwards.
 *
 * Dispatched by PropertyService::storeImages(), one job per uploaded image.
 * Run locally by the "queue" service in compose.yaml.
 */
class OptimisePropertyImage implements ShouldBeUnique, ShouldQueue
{
    // Queueable bundles: Dispatchable (::dispatch()), InteractsWithQueue
    // ($this->fail(), $this->attempts()), Queueable (onQueue(), delay()) and
    // SerializesModels (only the image id is stored in the jobs table).
    use Queueable;

    public const MAX_DIMENSION = 1600;

    public const WEBP_QUALITY = 80;

    /**
     * Try at most 3 times in total before giving up (then failed() runs).
     */
    public int $tries = 3;

    /**
     * Seconds to wait before retry 2 and retry 3. Waiting longer each time gives
     * a temporary problem (S3 hiccup, full disk being cleaned) time to clear.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * Kill the attempt if it runs longer than this many seconds.
     * Must be shorter than the queue connection's retry_after (90s), or a slow
     * job could be started a second time while the first is still running.
     */
    public int $timeout = 60;

    /**
     * If the image was deleted before a worker picked this job up, restoring the
     * model fails. Instead of counting that as a failure, just drop the job:
     * there is nothing left to optimise.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public PropertyImage $image)
    {
        // A separate queue, so many image jobs never delay quick ones on "default".
        // The worker must listen to it: queue:work --queue=images,default
        $this->onQueue('images');
    }

    /**
     * ShouldBeUnique: while a job for this image is waiting, dispatching another
     * one for the same image is ignored. Uses a cache lock keyed by this id.
     */
    public function uniqueId(): string
    {
        return (string) $this->image->id;
    }

    public function handle(): void
    {
        $path = $this->image->path;

        // Idempotent: a job can run twice (a retry, a duplicate dispatch, a worker
        // restart). Running it on an image that is already done changes nothing.
        if (str_ends_with($path, '.webp')) {
            return;
        }

        $disk = PropertyImage::disk();
        $contents = $disk->get($path);

        if ($contents === null) {
            // The file is gone (deleted by hand?). Retrying will not bring it back.
            $this->fail(new RuntimeException("Image file is missing: {$path}"));

            return;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            // Not a decodable image. Also permanent, so fail now instead of
            // wasting two more attempts.
            $this->fail(new RuntimeException("Image cannot be decoded: {$path}"));

            return;
        }

        $resized = $this->resize($this->applyExifOrientation($source, $contents));

        $webp = $this->encodeWebp($resized);
        $newPath = preg_replace('/\.[^.\/]+$/', '', $path).'.webp';

        // Order matters for safety:
        // 1. write the new file  2. point the row at it  3. delete the old file.
        // A crash at any step leaves a working image (worst case: one orphan file),
        // and the retry simply repeats the step that did not finish.
        $disk->put($newPath, $webp);

        $this->image->path = $newPath;
        // touch: false - optimising a photo is not an edit of the property,
        // so its updated_at ($touches on PropertyImage) is left alone.
        $this->image->save(['touch' => false]);

        $disk->delete($path);
    }

    /**
     * Called once, after the last attempt fails (or after $this->fail()).
     * The original image stays in place and keeps working; this only records why.
     */
    public function failed(?Throwable $exception): void
    {
        Log::warning('Property image could not be optimised', [
            'image_id' => $this->image->id,
            'property_id' => $this->image->property_id,
            'error' => $exception?->getMessage(),
        ]);
    }

    /**
     * Shrink so the longest side is MAX_DIMENSION, keeping the proportions.
     * Small images are never enlarged.
     */
    private function resize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::MAX_DIMENSION / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $resized === false ? $image : $resized;
    }

    /**
     * Phones save photos sideways and add an EXIF "Orientation" tag that says how
     * to rotate them. Re-encoding drops that tag, so apply the rotation to the
     * pixels first, otherwise portrait photos would appear sideways.
     */
    private function applyExifOrientation(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated === false ? $image : $rotated;
    }

    private function encodeWebp(GdImage $image): string
    {
        ob_start();
        $ok = imagewebp($image, null, self::WEBP_QUALITY);
        $webp = (string) ob_get_clean();

        if (! $ok || $webp === '') {
            // Unexpected and possibly temporary: throw, so the queue retries.
            throw new RuntimeException('WebP encoding failed.');
        }

        return $webp;
    }
}
