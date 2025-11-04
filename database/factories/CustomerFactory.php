<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'document' => fake()->unique()->numerify('###########'),
            'phone' => fake()->phoneNumber(),
            'address' => [
                'street' => fake()->streetName(),
                'number' => fake()->buildingNumber(),
                'complement' => fake()->optional()->secondaryAddress(),
                'neighborhood' => fake()->citySuffix(),
                'city' => fake()->city(),
                'state' => fake()->stateAbbr(),
                'zip_code' => fake()->postcode(),
            ],
            'status' => fake()->randomElement(['active', 'inactive', 'blocked']),
        ];
    }
}
