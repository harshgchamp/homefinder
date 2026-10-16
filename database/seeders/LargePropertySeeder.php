<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Seeder;

/**
 * Lots of properties, to try the chunked command and the CSV export on real volume.
 * Not called by DatabaseSeeder. Run it on purpose:
 *
 *   php artisan db:seed --class=LargePropertySeeder
 */
class LargePropertySeeder extends Seeder
{
    private const TOTAL = 5000;

    private const BATCH = 500;

    public function run(): void
    {
        // recycle(): reuse these existing agencies and types instead of the factory
        // creating a new agency and a new type for every single property.
        $agencies = Agency::all();
        $types = PropertyType::all();

        if ($agencies->isEmpty() || $types->isEmpty()) {
            $this->command->error('Run the normal seeders first (agencies and property types are needed).');

            return;
        }

        $bar = $this->command->getOutput()->createProgressBar(self::TOTAL);

        // Batches of 500 so memory stays flat: each batch of models is freed
        // before the next one is made.
        for ($done = 0; $done < self::TOTAL; $done += self::BATCH) {
            Property::factory()
                ->count(self::BATCH)
                ->recycle($agencies)
                ->recycle($types)
                // Many old listings, so properties:archive-stale has work to do.
                ->state(fn (): array => [
                    'published_at' => fake()->optional(0.8)->dateTimeBetween('-1 year', 'now'),
                ])
                ->create();

            $bar->advance(self::BATCH);
        }

        $bar->finish();
        $this->command->newLine();
    }
}
