<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $stock = Stock::factory()
            ->for($warehouse)
            ->for($product)
            ->create([
                'quantity' => 100.500,
                'reserved' => 10.250,
            ]);

        $this->assertDatabaseHas($stock->getTable(), [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 100.500,
            'reserved' => 10.250,
        ]);
    }

    public function test_cannot_create_stock_without_existing_warehouse(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        Stock::factory()->create([
            'warehouse_id' => 999999,
            'product_id' => $product->id,
        ]);
    }

    public function test_cannot_create_stock_without_existing_product(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->expectException(QueryException::class);

        Stock::factory()->create([
            'warehouse_id' => $warehouse->id,
            'product_id' => 999999,
        ]);
    }

    public function test_stock_warehouse_product_pair_must_be_unique(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        Stock::factory()->for($warehouse)->for($product)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Stock::factory()->for($warehouse)->for($product)->create();
    }

    public function test_same_product_can_have_stock_in_different_warehouses(): void
    {
        $product = Product::factory()->create();

        $firstStock = Stock::factory()->for(Warehouse::factory())->for($product)->create();
        $secondStock = Stock::factory()->for(Warehouse::factory())->for($product)->create();

        $this->assertModelExists($firstStock);
        $this->assertModelExists($secondStock);
        $this->assertNotSame($firstStock->warehouse_id, $secondStock->warehouse_id);
    }

    public function test_same_warehouse_can_have_stock_for_different_products(): void
    {
        $warehouse = Warehouse::factory()->create();

        $firstStock = Stock::factory()->for($warehouse)->for(Product::factory())->create();
        $secondStock = Stock::factory()->for($warehouse)->for(Product::factory())->create();

        $this->assertModelExists($firstStock);
        $this->assertModelExists($secondStock);
        $this->assertNotSame($firstStock->product_id, $secondStock->product_id);
    }
}
