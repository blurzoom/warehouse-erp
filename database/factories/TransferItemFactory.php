<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferItem>
 */
class TransferItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'transfer_id' => Transfer::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 0.001, 1000),
        ];
    }
}
