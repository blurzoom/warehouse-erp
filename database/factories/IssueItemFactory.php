<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueItem>
 */
class IssueItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 0.001, 1000),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
