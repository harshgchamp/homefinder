<?php

namespace App\Services;

use App\Models\Agency;
use Illuminate\Support\Str;

class AgencyService
{
    /**
     * @param  array{name: string, email: string, phone: string, is_active: bool}  $data  Validated input.
     */
    public function create(array $data): Agency
    {
        return Agency::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
        ]);
    }

    /**
     * @param  array{name: string, email: string, phone: string, is_active: bool}  $data  Validated input.
     */
    public function update(Agency $agency, array $data): Agency
    {
        $agency->fill($data);

        // Only regenerate the slug when the name actually changed.
        if ($agency->isDirty('name')) {
            $agency->slug = $this->uniqueSlug($data['name'], $agency->id);
        }

        $agency->save();

        return $agency;
    }

    /**
     * Whether the agency can be deleted. properties.agency_id is restrictOnDelete,
     * and that includes trashed properties (their rows still exist), so they count too.
     */
    public function canDelete(Agency $agency): bool
    {
        return ! $agency->properties()->withTrashed()->exists();
    }

    /**
     * Agents of this agency are kept: users.agency_id is nullOnDelete, so the
     * database turns them into normal users.
     */
    public function delete(Agency $agency): void
    {
        $agency->delete();
    }

    /**
     * "Acme Homes" and "Acme-Homes" are different names but the same slug,
     * so add -2, -3 and so on until the slug is free.
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (
            Agency::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query, int $id) => $query->whereKeyNot($id))
                ->exists()
        ) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
