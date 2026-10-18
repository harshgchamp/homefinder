<?php

namespace App\Services;

use App\Jobs\GeocodeProperty;
use App\Jobs\OptimisePropertyImage;
use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * All writes for properties live here, so the controller only deals with HTTP
 * (validate, call the service, redirect) and the same logic can be reused by
 * commands, jobs or an API later.
 */
class PropertyService
{
    /**
     * Keys in the validated data that are not columns on the properties table.
     */
    private const NON_COLUMN_KEYS = ['features', 'images', 'remove_images'];

    /**
     * Create a property with its features and images.
     *
     * DB::transaction(): the property row, the feature_property rows and the
     * property_images rows are saved together or not at all. Without it, an
     * error halfway would leave a property with no features or half its images.
     *
     * @param  array<string, mixed>  $data  Validated input.
     * @param  list<UploadedFile>  $images
     */
    public function create(array $data, array $images = []): Property
    {
        return $this->withUploadedFiles(function (array &$storedPaths) use ($data, $images): Property {
            return DB::transaction(function () use ($data, $images, &$storedPaths): Property {
                $property = Property::create(Arr::except($data, self::NON_COLUMN_KEYS));

                // Map position from the postcode, in the background (third-party API).
                GeocodeProperty::dispatch($property)->afterCommit();

                // sync(): make the pivot rows EXACTLY this list of ids.
                $property->features()->sync($this->featureIds($data));

                $this->storeImages($property, $images, $storedPaths);

                return $property;
            });
        });
    }

    /**
     * Update a property, its features, remove ticked images and add new ones.
     *
     * @param  array<string, mixed>  $data  Validated input.
     * @param  list<UploadedFile>  $newImages
     * @param  list<int>  $removeImageIds
     */
    public function update(Property $property, array $data, array $newImages = [], array $removeImageIds = []): Property
    {
        return $this->withUploadedFiles(function (array &$storedPaths) use ($property, $data, $newImages, $removeImageIds): Property {
            return DB::transaction(function () use ($property, $data, $newImages, $removeImageIds, &$storedPaths): Property {
                $property->update(Arr::except($data, self::NON_COLUMN_KEYS));

                // Only look the postcode up again if it actually changed.
                if ($property->wasChanged('postcode')) {
                    GeocodeProperty::dispatch($property)->afterCommit();
                }

                // sync() works out the difference itself: it detaches features
                // that were unticked and attaches new ones, leaving the rest alone.
                $property->features()->sync($this->featureIds($data));

                if ($removeImageIds !== []) {
                    // Delete through the model (not a mass delete) so
                    // PropertyImageObserver removes each file, after the commit.
                    // Scoping to $property->images() means ids from another
                    // property can never be deleted here.
                    $property->images()
                        ->whereKey($removeImageIds)
                        ->get()
                        ->each(fn (PropertyImage $image) => $image->delete());
                }

                $this->storeImages($property, $newImages, $storedPaths);

                return $property;
            });
        });
    }

    /**
     * Soft delete: sets deleted_at. Images and features stay, so it can be restored.
     */
    public function delete(Property $property): void
    {
        $property->delete();
    }

    public function restore(Property $property): void
    {
        $property->restore();
    }

    /**
     * Permanent delete. PropertyObserver::forceDeleting() removes the images
     * (and their files, after the commit); the database cascade removes
     * feature_property and favourites rows. Enquiries use restrictOnDelete,
     * so a property that has enquiries cannot be deleted for good: the
     * QueryException is caught in the controller and shown as a message.
     */
    public function forceDelete(Property $property): void
    {
        DB::transaction(fn () => $property->forceDelete());
    }

    /**
     * Apply one action to many properties at once.
     *
     * These are MASS queries: one UPDATE for all ids, instead of loading every
     * model and saving it. Much faster, but no model events or observers run and
     * mutators are skipped. That is fine here, because nothing listens for these
     * changes. If something later needed to react (send an email on publish),
     * this would have to loop over models instead.
     *
     * @param  'publish'|'unpublish'|'delete'  $action
     * @param  list<int>  $ids
     * @return int Number of rows changed.
     */
    public function bulk(string $action, array $ids): int
    {
        $query = Property::query()->whereKey($ids);

        return match ($action) {
            // Only drafts and scheduled ones: an already published date is kept.
            'publish' => $query->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '>', now()))
                ->update(['published_at' => now()]),
            'unpublish' => $query->whereNotNull('published_at')->update(['published_at' => null]),
            // On a SoftDeletes model, a mass delete() becomes UPDATE ... SET deleted_at = now.
            'delete' => $query->delete(),
        };
    }

    /**
     * Save uploaded files to the media disk and create one property_images row each.
     * New images go after the existing ones.
     *
     * @param  list<UploadedFile>  $files
     * @param  list<string>  $storedPaths  Filled in as files are written (see withUploadedFiles).
     */
    private function storeImages(Property $property, array $files, array &$storedPaths): void
    {
        if ($files === []) {
            return;
        }

        // reorder(): images() has ORDER BY sort_order built in. An aggregate does
        // not need it, and MySQL's ONLY_FULL_GROUP_BY mode can reject it.
        $max = $property->images()->reorder()->max('sort_order');
        $next = $max === null ? 0 : (int) $max + 1;

        $rows = [];

        foreach ($files as $file) {
            // store() picks a random unique file name, so two uploads called
            // "photo.jpg" never overwrite each other.
            $path = $file->store("properties/{$property->id}", ['disk' => config()->string('filesystems.media_disk')]);

            if ($path === false) {
                throw new \RuntimeException('Could not store the uploaded image.');
            }

            $storedPaths[] = $path;
            $rows[] = ['path' => $path, 'sort_order' => $next++];
        }

        // createMany(): one create() per row, each with property_id set by the relationship.
        $images = $property->images()->createMany($rows);

        // Hand the slow part (resize, WebP, strip GPS data) to the queue: one job
        // per image. afterCommit(): this runs inside DB::transaction(), so wait
        // until the rows are committed. Otherwise a fast worker could look for an
        // image that does not exist yet, or one that is about to be rolled back.
        foreach ($images as $image) {
            OptimisePropertyImage::dispatch($image)->afterCommit();
        }
    }

    /**
     * Files are not part of the database transaction. If the transaction fails
     * after some files were written, the rows roll back but the files would stay
     * on disk forever. This wrapper deletes those files and re-throws the error.
     *
     * @template T
     *
     * @param  callable(list<string>&): T  $callback
     * @return T
     */
    private function withUploadedFiles(callable $callback): mixed
    {
        /** @var list<string> $storedPaths */
        $storedPaths = [];

        try {
            return $callback($storedPaths);
        } catch (Throwable $e) {
            PropertyImage::disk()->delete($storedPaths);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function featureIds(array $data): array
    {
        /** @var list<int> $ids */
        $ids = $data['features'] ?? [];

        return $ids;
    }
}
