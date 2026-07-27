<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

$this->expectException(\Illuminate\Database\QueryException::class);

Stock::factory()->create([
'warehouse_id' => 999999,
'product_id' => $product->id,
]);
}

public function test_cannot_create_stock_without_existing_product(): void
{
$warehouse = Warehouse::factory()->create();

$this->expectException(\Illuminate\Database\QueryException::class);

Stock::factory()->create([
'warehouse_id' => $warehouse->id,
'product_id' => 999999,
]);
}
}
