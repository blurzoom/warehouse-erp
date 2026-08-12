<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_creates_stock_if_not_exists()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 10);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved' => 0,
        ]);
    }

    public function test_receive_increase_existing_stock()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 10);
        $stockService->increase($warehouse, $product, 5);
        $this->assertDatabaseHas('stocks', ['warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 15,
            'reserved' => 0, ]);
        $this->assertDatabaseCount('stocks', 1);
    }

    public function test_issue_decreases_stock_quantity()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 10);
        $stockService->decrease($warehouse, $product, 4);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 6,
        ]);
        $this->assertDatabaseCount('stocks', 1);
    }

    public function test_issue_throws_exception_when_stock_not_exists()
    {
        $this->expectException(\DomainException::class);

        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->decrease($warehouse, $product, 4);
    }

    public function test_issue_throws_exception_when_quantity_is_insufficient()
    {
        $this->expectException(\DomainException::class);

        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 10);

        try {
            $stockService->decrease($warehouse, $product, 15);
        } catch (\DomainException $e) {
            $this->assertDatabaseHas('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => 10,
            ]);

            throw $e;
        }
    }

    public function test_reserve_increases_reserved_quantity()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 10);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved' => 10,
        ]);
    }

    public function test_reserve_throws_exception_when_insufficient_available()
    {
        $this->expectException(\DomainException::class);

        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 10);
        $stockService->reserve($warehouse, $product, 95);
    }

    public function test_reserve_decreases_available_quantity()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 10);

        $stock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertEquals(90, $stock->quantity - $stock->reserved);
    }

    public function test_release_restores_available_quantity()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 10);
        $stockService->release($warehouse, $product, 10);

        $stock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        $this->assertEquals(100, $stock->quantity - $stock->reserved);
        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 100,
            'reserved' => 0,
        ]);
    }

    public function test_issue_throws_exception_when_reserved_stock_is_not_available()
    {
        $this->expectException(\DomainException::class);
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 80);
        try {
            $stockService->decrease($warehouse, $product, 30);
        } catch (\DomainException $e) {
            $this->assertDatabaseHas('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => 100,
                'reserved' => 80,
            ]);
            throw $e;
        }
    }

    public function test_issue_throws_exception_when_release_stock_is_not_reserve()
    {
        $this->expectException(\DomainException::class);
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stockService->reserve($warehouse, $product, 30);
        try {
            $stockService->release($warehouse, $product, 50);
        } catch (\DomainException $e) {
            $this->assertDatabaseHas('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'quantity' => 100,
                'reserved' => 30,
            ]);
            throw $e;
        }
    }

    public function test_reserve_all_available_quantity_is_allowed()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stock = $stockService->reserve($warehouse, $product, 100);
        $this->assertEquals(100, $stock->quantity);
        $this->assertEquals(100, $stock->reserved);
        $this->assertEquals(0, $stock->available());
    }

    public function test_issue_all_available_quantity_is_allowed()
    {
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stockService = new StockService;
        $stockService->increase($warehouse, $product, 100);
        $stock = $stockService->decrease($warehouse, $product, 100);
        $this->assertEquals(0, $stock->quantity);
        $this->assertEquals(0, $stock->reserved);
        $this->assertEquals(0, $stock->available());
    }

    public function test_transfer_requires_active_database_transaction(): void
    {
        $connection = DB::connection();
        $connection->rollBack();

        try {
            $sourceWarehouse = Warehouse::factory()->create();
            $destinationWarehouse = Warehouse::factory()->create();
            $product = Product::factory()->create();
            $category = $product->category;
            $unit = $product->unit;

            Stock::factory()
                ->for($sourceWarehouse)
                ->for($product)
                ->create([
                    'quantity' => 10,
                    'reserved' => 0,
                ]);

            $this->expectException(LogicException::class);
            $this->expectExceptionMessage('Stock transfer requires an active database transaction.');

            (new StockService)->transfer(
                $sourceWarehouse,
                $destinationWarehouse,
                [$product->id => 4.0],
            );
        } finally {
            Stock::query()->delete();
            $product->delete();
            $category->delete();
            $unit->delete();
            $sourceWarehouse->delete();
            $destinationWarehouse->delete();
            $connection->beginTransaction();
        }
    }

    public function test_transfer_moves_single_product_between_warehouses(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $sourceStock = Stock::factory()
            ->for($sourceWarehouse)
            ->for($product)
            ->create([
                'quantity' => 10,
                'reserved' => 0,
            ]);
        $stockService = new StockService;

        $this->assertDatabaseMissing('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
        ]);

        $result = DB::transaction(fn (): array => $stockService->transfer(
            $sourceWarehouse,
            $destinationWarehouse,
            [$product->id => 4.0],
        ));

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $sourceWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 6,
            'reserved' => 0,
        ]);
        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 4,
            'reserved' => 0,
        ]);

        $sourceStock = $sourceStock->fresh();
        $destinationStock = Stock::query()
            ->where('warehouse_id', $destinationWarehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertEquals(6, $sourceStock->quantity);
        $this->assertEquals(0, $sourceStock->reserved);
        $this->assertEquals(4, $destinationStock->quantity);
        $this->assertEquals(0, $destinationStock->reserved);
        $this->assertSame([
            $product->id => [
                'source_balance' => 6.0,
                'destination_balance' => 4.0,
            ],
        ], $result);
        $this->assertSame(1, Stock::query()
            ->where('warehouse_id', $sourceWarehouse->id)
            ->where('product_id', $product->id)
            ->count());
        $this->assertSame(1, Stock::query()
            ->where('warehouse_id', $destinationWarehouse->id)
            ->where('product_id', $product->id)
            ->count());
    }

    public function test_transfer_cannot_consume_reserved_stock(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $sourceStock = Stock::factory()
            ->for($sourceWarehouse)
            ->for($product)
            ->create([
                'quantity' => 10,
                'reserved' => 7,
            ]);
        $stockService = new StockService;

        $this->assertDatabaseMissing('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
        ]);

        $exception = null;

        try {
            DB::transaction(fn (): array => $stockService->transfer(
                $sourceWarehouse,
                $destinationWarehouse,
                [$product->id => 4.0],
            ));
        } catch (\DomainException $caughtException) {
            $exception = $caughtException;
        }

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Insufficient stock quantity', $exception->getMessage());

        $sourceStock = $sourceStock->fresh();

        $this->assertEquals(10, $sourceStock->quantity);
        $this->assertEquals(7, $sourceStock->reserved);
        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $sourceWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved' => 7,
        ]);
        $this->assertDatabaseMissing('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_transfer_all_available_quantity_is_allowed(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $sourceStock = Stock::factory()
            ->for($sourceWarehouse)
            ->for($product)
            ->create([
                'quantity' => 10,
                'reserved' => 7,
            ]);
        $stockService = new StockService;

        $this->assertDatabaseMissing('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
        ]);

        $result = DB::transaction(fn (): array => $stockService->transfer(
            $sourceWarehouse,
            $destinationWarehouse,
            [$product->id => 3.0],
        ));

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $sourceWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 7,
            'reserved' => 7,
        ]);
        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $destinationWarehouse->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'reserved' => 0,
        ]);

        $sourceStock = $sourceStock->fresh();
        $destinationStock = Stock::query()
            ->where('warehouse_id', $destinationWarehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertEquals(7, $sourceStock->quantity);
        $this->assertEquals(7, $sourceStock->reserved);
        $this->assertEquals(0, $sourceStock->available());
        $this->assertEquals(3, $destinationStock->quantity);
        $this->assertEquals(0, $destinationStock->reserved);
        $this->assertSame([
            $product->id => [
                'source_balance' => 7.0,
                'destination_balance' => 3.0,
            ],
        ], $result);
    }

    /*
reserve() рівно весь доступний залишок (має бути дозволено);
issue() рівно весь доступний залишок (має бути дозволено).
     */
}
