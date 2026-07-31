<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
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
            'number' => fake()->unique()->bothify('ISS-########'),
            'issue_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'status' => 'draft',
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
