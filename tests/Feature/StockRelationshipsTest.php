<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_belongs_to_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create(['address' => 'test address']);
        $stock = Stock::factory()->for($warehouse)->create();

        $this->assertInstanceOf(Warehouse::class, $stock->warehouse);
        $this->assertTrue($warehouse->is($stock->warehouse));
    }

    public function test_stock_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $stock = Stock::factory()->for($product)->create();

        $this->assertInstanceOf(Product::class, $stock->product);
        $this->assertSame($product->id, $stock->product->id);
    }

    public function test_warehouse_has_many_stocks(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stock1 = Stock::factory()->for($warehouse)->for($product)->create();
        $stock2 = Stock::factory()->for($warehouse)->for(Product::factory())->create();
        $stock3 = Stock::factory()->for($warehouse)->for(Product::factory())->create();

        $stocks = $warehouse->stocks;

        $this->assertCount(3, $stocks);
        $this->assertTrue($warehouse->stocks->contains($stock1));
        $this->assertTrue($stocks->contains($stock2));
        $this->assertTrue($stocks->contains($stock3));
    }

    public function test_product_has_many_stocks(): void
    {
        $product = Product::factory()->create();
        $warehouse1 = Warehouse::factory()->create(['address' => 'test address']);
        $warehouse2 = Warehouse::factory()->create(['address' => 'test address']);

        $stock1 = Stock::factory()->for($product)->for($warehouse1)->create();
        $stock2 = Stock::factory()->for($product)->for($warehouse2)->create();

        $stocks = $product->stocks;

        $this->assertCount(2, $stocks);
        $this->assertTrue($stocks->contains($stock1));
        $this->assertTrue($stocks->contains($stock2));
    }
}
