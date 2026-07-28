<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceiptItem>
 */
class ReceiptItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'receipt_id' => Receipt::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 0.001, 1000),
            'unit_cost' => fake()->randomFloat(2, 0.01, 10000),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
