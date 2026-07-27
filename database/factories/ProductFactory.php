<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'barcode' => null,
            'name' => fake()->unique()->words(2, true),
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'category_id' => Category::factory(),
            'unit_id' => Unit::factory(),
        ];
    }
}
