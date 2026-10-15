<?php

namespace App\Services;

use App\Models\Feature;
use Illuminate\Support\Str;

class FeatureService
{
    /**
     * @param  array{name: string}  $data  Validated input.
     */
    public function create(array $data): Feature
    {
        return Feature::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
        ]);
    }

    /**
     * @param  array{name: string}  $data  Validated input.
     */
    public function update(Feature $feature, array $data): Feature
    {
        $feature->fill(['name' => $data['name']]);

        // Only regenerate the slug when the name actually changed.
        if ($feature->isDirty('name')) {
            $feature->slug = $this->uniqueSlug($data['name'], $feature->id);
        }

        $feature->save();

        return $feature;
    }

    /**
     * Rows in feature_property are removed by the database (cascade on delete).
     */
    public function delete(Feature $feature): void
    {
        $feature->delete();
    }

    /**
     * "Pets allowed" and "Pets-allowed" are different names but the same slug,
     * so add -2, -3 and so on until the slug is free.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            Feature::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
