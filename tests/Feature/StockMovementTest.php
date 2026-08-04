<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_a_stock_movement_with_valid_data(): void
    {
        $movement = StockMovement::factory()->create([
            'quantity' => 12.345,
            'balance_after' => 98.765,
        ]);

        $this->assertModelExists($movement);
        $this->assertSame(StockMovementType::Receipt, $movement->type);
        $this->assertSame('12.345', $movement->quantity);
        $this->assertSame('98.765', $movement->balance_after);
        $this->assertNull($movement->created_by);
        $this->assertFalse(Schema::hasColumn('stock_movements', 'updated_at'));
    }

    public function test_stock_movement_supports_negative_quantities(): void
    {
        $movement = StockMovement::factory()->create([
            'type' => StockMovementType::Issue,
            'quantity' => -12.345,
        ]);

        $this->assertSame(StockMovementType::Issue, $movement->type);
        $this->assertSame('-12.345', $movement->quantity);
    }

    public function test_stock_movement_requires_existing_warehouse(): void
    {
        $this->expectException(QueryException::class);

        StockMovement::factory()->create([
            'warehouse_id' => 999999,
            'product_id' => Product::factory(),
        ]);
    }

    public function test_stock_movement_requires_existing_product(): void
    {
        $this->expectException(QueryException::class);

        StockMovement::factory()->create([
            'product_id' => 999999,
        ]);
    }

    public function test_stock_movement_requires_source_type(): void
    {
        $this->expectException(QueryException::class);

        StockMovement::factory()->create([
            'source_type' => null,
        ]);
    }

    public function test_stock_movement_requires_source_id(): void
    {
        $this->expectException(QueryException::class);

        StockMovement::factory()->create([
            'source_id' => null,
        ]);
    }

    public function test_existing_stock_movement_cannot_be_updated(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stock movements are immutable.');

        $movement->update(['quantity' => 50]);
    }

    public function test_existing_stock_movement_cannot_be_deleted(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Stock movements are immutable.');

        $movement->delete();
    }
}
