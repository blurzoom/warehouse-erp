<?php

namespace Database\Factories;

use App\Models\Stock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stock>
 */
class StockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'product_id' => \App\Models\Product::factory(),
            'quantity' => fake()->randomFloat(3, 0, 1000),
            'reserved' => fake()->randomFloat(3, 0, 100),
        ];
    }
}
