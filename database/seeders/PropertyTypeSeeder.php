<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PropertyTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'Detached house',
            'Semi-detached house',
            'Terraced house',
            'Flat',
            'Maisonette',
            'Bungalow',
        ];

        foreach ($types as $name) {
            PropertyType::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
