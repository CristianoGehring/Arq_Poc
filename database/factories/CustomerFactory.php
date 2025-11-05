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
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'document' => $this->faker->unique()->numerify('###########'), // CPF
            'phone' => $this->faker->phoneNumber(),
            'address' => [
                'street' => $this->faker->streetName(),
                'number' => $this->faker->buildingNumber(),
                'city' => $this->faker->city(),
                'state' => $this->faker->stateAbbr(),
                'zip_code' => $this->faker->postcode(),
            ],
        ];
    }
}
