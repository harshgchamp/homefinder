<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FeatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            'Garden',
            'Parking',
            'Garage',
            'Balcony',
            'Lift',
            'Wheelchair accessible',
            'Pets allowed',
        ];

        foreach ($features as $name) {
            Feature::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }
    }
}
