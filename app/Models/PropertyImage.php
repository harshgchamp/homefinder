<?php

namespace App\Models;

use App\Observers\PropertyImageObserver;
use Database\Factories\PropertyImageFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $property_id
 * @property string $path
 * @property int $sort_order
 * @property-read string $url
 */
#[ObservedBy(PropertyImageObserver::class)]
class PropertyImage extends Model
{
    /** @use HasFactory<PropertyImageFactory> */
    use HasFactory;

    protected $fillable = ['path', 'sort_order'];

    /**
     * When an image is created, updated or deleted, also bump the parent
     * property's updated_at, so "last updated" on the property stays truthful.
     *
     * @var list<string>
     */
    protected $touches = ['property'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * The disk images live on. "public" locally, "s3" in production:
     * set MEDIA_DISK in .env, no code change needed.
     */
    public static function disk(): Filesystem
    {
        return Storage::disk(config()->string('filesystems.media_disk'));
    }

    /**
     * Full URL for an <img src>. Only the path is stored in the database,
     * because the URL depends on the disk (local, S3, CDN) and may change.
     *
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::make(
            get: fn (): string => static::disk()->url($this->path),
        );
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
}
