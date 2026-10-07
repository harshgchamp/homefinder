<?php

namespace Database\Factories;

use App\Models\Agency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agency>
 */
class AgencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'email' => fake()->unique()->companyEmail(),
            'slug' => Str::slug($name),
            'phone' => fake()->unique()->phoneNumber(),
            'logo_path' => null,
            'is_active' => true,
        ];
    }
}
