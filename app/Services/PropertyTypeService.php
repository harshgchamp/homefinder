<?php

namespace App\Services;

use App\Models\PropertyType;
use Illuminate\Support\Str;

class PropertyTypeService
{
    /**
     * @param  array{name: string}  $data  Validated input.
     */
    public function create(array $data): PropertyType
    {
        return PropertyType::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);
    }

    /**
     * @param  array{name: string}  $data  Validated input.
     */
    public function update(PropertyType $type, array $data): PropertyType
    {
        $type->fill(['name' => $data['name']]);

        // Only regenerate the slug when the name actually changed.
        if ($type->isDirty('name')) {
            $type->slug = $this->uniqueSlug($data['name'], $type->id);
        }

        $type->save();

        return $type;
    }

    /**
     * properties.property_type_id is restrictOnDelete: the database refuses to
     * delete a type that is still in use. Check first, so the user gets a clear
     * message instead of a 500 error page.
     *
     * withTrashed(): soft-deleted properties still hold the foreign key.
     */
    public function canDelete(PropertyType $type): bool
    {
        return ! $type->properties()->withTrashed()->exists();
    }

    /**
     * ClearsLookupCache (on the model) empties the cached dropdown list, so the
     * property form stops offering the deleted type straight away.
     */
    public function delete(PropertyType $type): void
    {
        $type->delete();
    }

    /**
     * "Semi detached" and "Semi-detached" are different names but the same slug,
     * so add -2, -3 and so on until the slug is free.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            PropertyType::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
