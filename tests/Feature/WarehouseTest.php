<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Основний склад',
        ]);
        $this->assertDatabaseHas($warehouse->getTable(), [
            'name' => 'Основний склад',
        ]);
    }

    public function test_can_create_two_warehouses_with_same_name()
    {
        Warehouse::factory()->create([
            'name' => 'Основний склад',
        ]);

        Warehouse::factory()->create([
            'name' => 'Основний склад',
        ]);

        $this->assertDatabaseCount('warehouses', 2);
    }
}
