<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'ORD-'.fake()->unique()->numerify('######'),
            'customer_email' => fake()->safeEmail(),
            'total_cents' => fake()->numberBetween(500, 250_000),
        ];
    }
}
