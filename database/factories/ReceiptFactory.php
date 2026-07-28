<?php

namespace Database\Factories;

use App\Models\Receipt;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receipt>
 */
class ReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => Warehouse::factory(),
            'number' => fake()->unique()->bothify('RCPT-########'),
            'receipt_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => 'draft',
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
