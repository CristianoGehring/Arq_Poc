<?php

namespace Database\Factories;

use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use App\Models\Charge;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChargeFactory extends Factory
{
    protected $model = Charge::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'description' => $this->faker->sentence,
            'payment_method' => $this->faker->randomElement(PaymentMethod::cases()),
            'status' => $this->faker->randomElement(ChargeStatus::cases()),
            'due_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'paid_at' => $this->faker->boolean(50) ? $this->faker->dateTimeBetween('-15 days', 'now') : null,
            'metadata' => $this->faker->boolean(30) ? ['notes' => $this->faker->sentence] : null,
        ];
    }

    public function paid(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::PAID,
            'paid_at' => now(),
        ]);
    }

    public function pending(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::PENDING,
            'paid_at' => null,
        ]);
    }

    public function cancelled(): Factory
    {
        return $this->state(fn (array $attributes) => [
            'status' => ChargeStatus::CANCELLED,
            'paid_at' => null,
        ]);
    }
}
