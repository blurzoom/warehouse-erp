<?php

namespace Database\Factories;

use App\Enums\TransferStatus;
use App\Models\Transfer;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->bothify('TRF-########'),
            'transfer_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'from_warehouse_id' => Warehouse::factory(),
            'to_warehouse_id' => Warehouse::factory(),
            'status' => TransferStatus::Draft,
        ];
    }
}
