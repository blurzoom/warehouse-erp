<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\StockReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_stock_and_movement_totals_produce_no_discrepancy(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 50.000,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(0, $discrepancies);
    }

    public function test_lower_actual_stock_quantity_is_detected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 45.000,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 50.000,
            'actual_quantity' => 45.000,
            'difference' => -5.000,
        ], $discrepancies->first());
    }

    public function test_higher_actual_stock_quantity_is_detected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 55.000,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 50.000,
            'actual_quantity' => 55.000,
            'difference' => 5.000,
        ], $discrepancies->first());
    }

    public function test_movements_without_stock_record_are_detected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 50.000,
            'actual_quantity' => 0.000,
            'difference' => -50.000,
        ], $discrepancies->first());
    }

    public function test_stock_record_without_movements_is_detected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 50.000,
            'reserved' => 0.000,
        ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 0.000,
            'actual_quantity' => 50.000,
            'difference' => 50.000,
        ], $discrepancies->first());
    }

    public function test_multiple_warehouse_and_product_pairs_are_reconciled_independently(): void
    {
        $firstWarehouse = Warehouse::factory()->create();
        $secondWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $firstReceipt = Receipt::factory()->create([
            'warehouse_id' => $firstWarehouse->id,
        ]);

        $secondReceipt = Receipt::factory()->create([
            'warehouse_id' => $secondWarehouse->id,
        ]);

        Stock::factory()->create([
            'warehouse_id' => $firstWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 50.000,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($firstReceipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        Stock::factory()->create([
            'warehouse_id' => $secondWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 30.000,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($secondReceipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 40.000,
                'balance_after' => 40.000,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $secondWarehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 40.000,
            'actual_quantity' => 30.000,
            'difference' => -10.000,
        ], $discrepancies->first());
    }

    public function test_fractional_quantities_with_three_decimal_places_are_compared_correctly(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10.124,
            'reserved' => 0.000,
        ]);

        StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 10.125,
                'balance_after' => 10.125,
            ]);

        $discrepancies = app(StockReconciliationService::class)->reconcile();

        $this->assertCount(1, $discrepancies);

        $this->assertEquals([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'expected_quantity' => 10.125,
            'actual_quantity' => 10.124,
            'difference' => -0.001,
        ], $discrepancies->first());
    }

    public function test_reconciliation_does_not_modify_database_records(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $receipt = Receipt::factory()->create([
            'warehouse_id' => $warehouse->id,
        ]);

        $stock = Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 45.000,
            'reserved' => 0.000,
        ]);

        $movement = StockMovement::factory()
            ->forReceipt($receipt)
            ->create([
                'product_id' => $product->id,
                'quantity' => 50.000,
                'balance_after' => 50.000,
            ]);

        $stock->refresh();
        $movement->refresh();

        $stockBefore = $stock->getAttributes();
        $movementBefore = $movement->getAttributes();

        app(StockReconciliationService::class)->reconcile();

        $this->assertSame(
            $stockBefore,
            $stock->fresh()->getAttributes()
        );

        $this->assertSame(
            $movementBefore,
            $movement->fresh()->getAttributes()
        );

        $this->assertDatabaseCount('stocks', 1);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
