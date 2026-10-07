<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => Property::factory()->published(),
            'user_id' => User::factory(),
            'message' => fake()->paragraph(),
            'phone' => fake()->optional()->phoneNumber(),
        ];
    }
}
