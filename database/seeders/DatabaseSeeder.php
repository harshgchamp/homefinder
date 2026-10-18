<?php

namespace Database\Seeders;

use App\Support\CachedLookups;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * While running seeds, you may want to prevent models from dispatching events. You may achieve this using the WithoutModelEvents trait. When used, the WithoutModelEvents trait ensures no model events are dispatched, even if additional seed classes are executed via the call method.
     */
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PropertyTypeSeeder::class,
            FeatureSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }

        // WithoutModelEvents (above) means the models' cache-clearing events did
        // not run, so clear the cached dropdown lists by hand.
        CachedLookups::forgetAll();
    }
}
