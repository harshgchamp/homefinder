<?php

use App\Jobs\OptimisePropertyImage;
use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * A real JPEG of the given size, as raw bytes. With $gpsMarker, an EXIF (APP1)
 * segment containing that text is inserted, like a phone photo's metadata.
 */
function jpegBytes(int $width, int $height, ?string $gpsMarker = null): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    if ($gpsMarker === null) {
        return $jpeg;
    }

    $payload = "Exif\0\0".$gpsMarker;
    $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

    // Right after the 2-byte start-of-image marker, where cameras put EXIF.
    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}

function imageWithFile(string $bytes, string $path = 'properties/1/photo.jpg'): PropertyImage
{
    Storage::disk('public')->put($path, $bytes);

    return PropertyImage::factory()->create(['path' => $path]);
}

// --- Dispatching ----------------------------------------------------------------

test('uploading images queues one job per image on the images queue', function () {
    // Queue::fake(): jobs are recorded, not run.
    Queue::fake();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.properties.store'), [
            'agency_id' => Agency::factory()->create()->id,
            'property_type_id' => PropertyType::factory()->create()->id,
            'title' => 'Three bed house',
            'description' => 'A lovely family home close to the station and good schools.',
            'listing_type' => 'sale',
            'status' => 'available',
            'price' => 350000,
            'bedrooms' => 3,
            'bathrooms' => 1,
            'address_line_1' => '12 High Street',
            'city' => 'Romford',
            'postcode' => 'RM1 1AA',
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
        ])
        ->assertRedirect();

    Queue::assertPushedOn('images', OptimisePropertyImage::class);
    Queue::assertPushed(OptimisePropertyImage::class, 2);

    $ids = Property::sole()->images()->pluck('id')->all();
    Queue::assertPushed(OptimisePropertyImage::class, fn (OptimisePropertyImage $job) => in_array($job->image->id, $ids, true));
});

test('no job runs when the transaction that dispatched it rolls back', function () {
    // The test queue connection is "sync" (runs jobs immediately), which
    // respects afterCommit(): the job waits for COMMIT, and a ROLLBACK cancels it.
    $image = imageWithFile(jpegBytes(2000, 1000));

    try {
        DB::transaction(function () use ($image): void {
            OptimisePropertyImage::dispatch($image)->afterCommit();

            throw new RuntimeException('Something failed after dispatching.');
        });
    } catch (RuntimeException) {
        // expected
    }

    expect($image->fresh()->path)->toBe('properties/1/photo.jpg');
});

// --- What the job does ----------------------------------------------------------

test('a large photo is resized, converted to WebP, and the original removed', function () {
    $image = imageWithFile(jpegBytes(3200, 2400));

    OptimisePropertyImage::dispatchSync($image);

    $image->refresh();
    expect($image->path)->toBe('properties/1/photo.webp');

    Storage::disk('public')->assertMissing('properties/1/photo.jpg');
    Storage::disk('public')->assertExists('properties/1/photo.webp');

    $size = getimagesizefromstring(Storage::disk('public')->get($image->path));
    expect($size['mime'])->toBe('image/webp')
        ->and($size[0])->toBe(1600)   // longest side capped
        ->and($size[1])->toBe(1200);  // proportions kept (4:3)
});

test('small images are converted but never enlarged', function () {
    $image = imageWithFile(jpegBytes(800, 600));

    OptimisePropertyImage::dispatchSync($image);

    $size = getimagesizefromstring(Storage::disk('public')->get($image->fresh()->path));
    expect([$size[0], $size[1]])->toBe([800, 600]);
});

test('EXIF data such as GPS location is removed', function () {
    $image = imageWithFile(jpegBytes(1000, 800, gpsMarker: 'GPS-51.5072N-0.1276W'));
    expect(Storage::disk('public')->get('properties/1/photo.jpg'))->toContain('GPS-51.5072N');

    OptimisePropertyImage::dispatchSync($image);

    expect(Storage::disk('public')->get($image->fresh()->path))
        ->not->toContain('GPS-51.5072N')
        ->not->toContain('Exif');
});

test('running the job twice is harmless (idempotent)', function () {
    $image = imageWithFile(jpegBytes(2000, 1000));

    OptimisePropertyImage::dispatchSync($image);
    $first = Storage::disk('public')->get($image->fresh()->path);

    OptimisePropertyImage::dispatchSync($image->fresh());

    expect($image->fresh()->path)->toBe('properties/1/photo.webp')
        ->and(Storage::disk('public')->get('properties/1/photo.webp'))->toBe($first)
        ->and(Storage::disk('public')->allFiles('properties/1'))->toBe(['properties/1/photo.webp']);
});

test('optimising does not change the property updated_at', function () {
    $property = Property::factory()->create();
    Storage::disk('public')->put('properties/1/photo.jpg', jpegBytes(500, 500));
    $image = $property->images()->create(['path' => 'properties/1/photo.jpg']);

    // Creating the image touched the property, so set updated_at back with a
    // direct query (a model save would see "no change" and skip it).
    Property::query()->whereKey($property->id)->toBase()->update(['updated_at' => now()->subYear()]);

    OptimisePropertyImage::dispatchSync($image);

    expect($property->fresh()->updated_at->isBefore(now()->subMonths(11)))->toBeTrue();
});

// --- When things go wrong -------------------------------------------------------

test('a job for an image deleted in the meantime is dropped quietly', function () {
    $image = imageWithFile(jpegBytes(500, 500));
    $job = new OptimisePropertyImage($image);
    PropertyImage::query()->whereKey($image->id)->delete();

    // dispatch() on the sync queue serialises the job (only the id is stored),
    // then restores the model, which no longer exists. $deleteWhenMissingModels
    // turns that into "drop the job" instead of an exception.
    dispatch($job);

    expect(PropertyImage::count())->toBe(0);
})->throwsNoExceptions();

test('an unreadable file fails the job immediately, without retries', function () {
    $image = imageWithFile('this is not an image');

    // withFakeQueueInteractions(): lets the test check $this->fail() calls.
    $job = (new OptimisePropertyImage($image))->withFakeQueueInteractions();
    $job->handle();

    $job->assertFailed();
    expect($image->fresh()->path)->toBe('properties/1/photo.jpg');
});

test('failed() logs which image could not be optimised', function () {
    $image = PropertyImage::factory()->create();

    Log::shouldReceive('warning')->once()->with(
        'Property image could not be optimised',
        Mockery::on(fn (array $context) => $context['image_id'] === $image->id && $context['error'] === 'Disk unavailable'),
    );

    (new OptimisePropertyImage($image))->failed(new RuntimeException('Disk unavailable'));
});

test('the job is configured for retries, a timeout and its own queue', function () {
    $job = new OptimisePropertyImage(PropertyImage::factory()->create());

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 60])
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->queue)->toBe('images')
        ->and($job->uniqueId())->toBe((string) $job->image->id);
});
