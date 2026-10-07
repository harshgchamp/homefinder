<?php

namespace Database\Factories;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Models\Agency;
use App\Models\Property;
use App\Models\PropertyType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $bedrooms = fake()->numberBetween(0, 5);
        $title = ($bedrooms === 0 ? 'Studio' : "{$bedrooms} bedroom").' '.fake()->randomElement(['flat', 'house', 'maisonette']);

        return [
            'agency_id' => Agency::factory(),
            'property_type_id' => PropertyType::factory(),
            'title' => ucfirst($title),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => fake()->paragraphs(3, true),
            'listing_type' => ListingType::Sale,
            'price' => fake()->numberBetween(150, 900) * 1000,
            'bedrooms' => $bedrooms,
            'bathrooms' => fake()->numberBetween(1, 3),
            'furnished' => null,
            'address_line_1' => fake()->buildingNumber().' '.fake()->streetName(),
            'city' => fake()->randomElement(['Romford', 'Ilford', 'Barking', 'Chelmsford', 'London']),
            'postcode' => fake()->postcode(),
            'status' => PropertyStatus::Available,
            'published_at' => null,
        ];
    }

    // --- Listing type ---------------------------------------------------

    public function forSale(): static
    {
        return $this->state(fn (): array => [
            'listing_type' => ListingType::Sale,
            'price' => fake()->numberBetween(150, 900) * 1000,
            'furnished' => null,
        ]);
    }

    public function forRent(): static
    {
        return $this->state(fn (): array => [
            'listing_type' => ListingType::Rent,
            'price' => fake()->numberBetween(8, 35) * 100,
            'furnished' => fake()->boolean(),
        ]);
    }

    // --- Publication ----------------------------------------------------

    public function draft(): static
    {
        return $this->state(fn (): array => ['published_at' => null]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'published_at' => fake()->dateTimeBetween('-60 days', 'now'),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (): array => [
            'published_at' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    // --- Market status (keeps the enum rule valid) -----------------------

    public function sold(): static
    {
        return $this->forSale()->state(fn (): array => ['status' => PropertyStatus::Sold]);
    }

    public function let(): static
    {
        return $this->forRent()->state(fn (): array => ['status' => PropertyStatus::Let]);
    }
}
