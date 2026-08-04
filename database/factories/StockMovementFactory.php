<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Issue;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
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
            'product_id' => Product::factory(),
            'type' => StockMovementType::Receipt,
            'quantity' => fake()->randomFloat(3, 0.001, 1000),
            'balance_after' => fake()->randomFloat(3, 0, 1000),
            'source_type' => Receipt::class,
            'source_id' => Receipt::factory(),
            'created_by' => null,
        ];
    }

    public function forReceipt(Receipt $receipt): static
    {
        return $this->state([
            'warehouse_id' => $receipt->warehouse_id,
            'source_type' => $receipt->getMorphClass(),
            'source_id' => $receipt->getKey(),
            'type' => StockMovementType::Receipt,
        ]);
    }

    public function forIssue(Issue $issue): static
    {
        return $this->state([
            'warehouse_id' => $issue->warehouse_id,
            'source_type' => $issue->getMorphClass(),
            'source_id' => $issue->getKey(),
            'type' => StockMovementType::Issue,
        ]);
    }
}
