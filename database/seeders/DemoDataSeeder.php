<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Enquiry;
use App\Models\Feature;
use App\Models\Property;
use App\Models\PropertyImage;
use App\Models\PropertyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $types = PropertyType::all();
        $features = Feature::all();

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'harsh.gandhi01@hotmail.com',
            'password' => Hash::make('123123123123'),
        ]);

        $users = User::factory(10)->create();

        $images = PropertyImage::factory()
            ->count(3)
            ->sequence(fn (Sequence $sequence): array => ['sort_order' => $sequence->index]);

        Agency::factory(5)->create()->each(function (Agency $agency) use ($types, $images): void {
            $base = Property::factory()
                ->for($agency)
                ->has($images, 'images')
                ->state(fn (): array => ['property_type_id' => $types->random()->id]);

            $base->count(6)->forSale()->published()->create();
            $base->count(4)->forRent()->published()->create();
            $base->count(1)->sold()->published()->create();
            $base->count(1)->let()->published()->create();
            $base->count(2)->draft()->create();
            $base->count(1)->scheduled()->create();
        });

        Property::all()->each(function (Property $property) use ($features): void {
            $property->features()->attach(
                $features->random(fake()->numberBetween(1, 4))->pluck('id')
            );
        });

        $published = Property::query()->published()->get();

        $users->each(function (User $user) use ($published): void {
            $user->favourites()->attach($published->random(3)->pluck('id'));

            Enquiry::factory()
                ->count(2)
                ->for($user)
                ->sequence(fn (): array => ['property_id' => $published->random()->id])
                ->create();
        });
    }
}
