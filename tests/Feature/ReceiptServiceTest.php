<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\ReceiptService;
use App\Services\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ReceiptServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $this->assertInstanceOf(Receipt::class, $receipt);
        $this->assertDatabaseHas('receipts', [
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'status' => 'draft',
        ]);
    }

    public function test_add_item_to_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $item = $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $this->assertInstanceOf(ReceiptItem::class, $item);
        $this->assertEquals($receipt->id, $item->receipt_id);
        $this->assertEquals($product->id, $item->product_id);
        $this->assertEquals(5.000, $item->quantity);
        $this->assertEquals(120.50, $item->unit_cost);

        $this->assertDatabaseHas('receipt_items', [
            'receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'quantity' => 5.000,
            'unit_cost' => 120.50,
        ]);
    }

    public function test_update_receipt_item(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $item = $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $updatedItem = $service->updateItem($item, [
            'quantity' => 10,
            'unit_cost' => 250.75,
            'comment' => 'Updated item',
        ]);

        $this->assertSame($item->id, $updatedItem->id);
        $this->assertEquals(10.000, $updatedItem->quantity);
        $this->assertEquals(250.75, $updatedItem->unit_cost);
        $this->assertEquals('Updated item', $updatedItem->comment);

        $this->assertDatabaseHas('receipt_items', [
            'id' => $item->id,
            'quantity' => 10.000,
            'unit_cost' => 250.75,
            'comment' => 'Updated item',
        ]);
    }

    public function test_remove_receipt_item(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $item = $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->removeItem($item);

        $this->assertDatabaseMissing('receipt_items', [
            'id' => $item->id,
        ]);
    }

    public function test_cannot_post_empty_receipt(): void
    {
        $this->expectException(DomainException::class);

        $warehouse = Warehouse::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        try {
            $service->post($receipt);
        } catch (DomainException $e) {
            $this->assertDatabaseHas('receipts', [
                'id' => $receipt->id,
                'status' => 'draft',
            ]);

            throw $e;
        }
    }

    public function test_cannot_post_non_draft_receipt(): void
    {
        $this->expectException(DomainException::class);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $receipt->update(['status' => 'posted']);

        try {
            $service->post($receipt->fresh());
        } catch (DomainException $e) {
            $this->assertDatabaseHas('receipts', [
                'id' => $receipt->id,
                'status' => 'posted',
            ]);

            throw $e;
        }
    }

    public function test_post_receipt_changes_status_to_posted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'posted',
        ]);
    }

    public function test_post_receipt_increases_stock(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'posted',
        ]);

        $this->assertDatabaseCount('stocks', 1);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => 10,
        ]);
    }

    public function test_post_receipt_is_atomic(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $receipt = Receipt::factory()
            ->for($warehouse)
            ->create([
                'number' => 'RCPT-00000001',
                'receipt_date' => '2026-07-27',
                'comment' => 'Incoming goods',
            ]);

        ReceiptItem::factory()
            ->for($receipt)
            ->for($product)
            ->create([
                'quantity' => 10,
                'unit_cost' => 120.50,
                'comment' => 'Test item',
            ]);

        $mockStockService = Mockery::mock(StockService::class);
        $mockStockService->shouldReceive('increase')
            ->once()
            ->andThrow(new DomainException('Stock update failed'));

        $this->app->instance(StockService::class, $mockStockService);

        $service = app(ReceiptService::class);

        $this->expectException(DomainException::class);

        try {
            $service->post($receipt);
        } catch (DomainException $e) {
            $this->assertSame('draft', $receipt->fresh()->status);

            $this->assertDatabaseHas('receipts', [
                'id' => $receipt->id,
                'status' => 'draft',
            ]);

            $this->assertDatabaseCount('stocks', 0);

            throw $e;
        }
    }

    public function test_cannot_update_item_in_posted_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $item = $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $stock = Stock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($stock);

        $this->expectException(DomainException::class);

        try {
            $service->updateItem($item->fresh(), [
                'quantity' => 10,
                'unit_cost' => 250.75,
                'comment' => 'Updated item',
            ]);
        } catch (DomainException $e) {
            $this->assertSame('posted', $receipt->fresh()->status);
            $this->assertCount(1, $receipt->fresh()->items);
            $this->assertEquals($stock->quantity, $stock->fresh()->quantity);

            throw $e;
        }
    }

    public function test_cannot_remove_item_from_posted_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $item = $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $stock = Stock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertNotNull($stock);

        $this->expectException(DomainException::class);

        try {
            $service->removeItem($item->fresh());
        } catch (DomainException $e) {
            $this->assertSame('posted', $receipt->fresh()->status);
            $this->assertCount(1, $receipt->fresh()->items);
            $this->assertEquals($stock->quantity, $stock->fresh()->quantity);

            throw $e;
        }
    }

    public function test_cannot_add_item_to_posted_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();
        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $service->addItem($receipt, [
            'product_id' => $firstProduct->id,
            'quantity' => 5,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $firstStock = Stock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $firstProduct->id)
            ->first();

        $this->assertNotNull($firstStock);

        $this->expectException(DomainException::class);

        try {
            $service->addItem($receipt->fresh(), [
                'product_id' => $secondProduct->id,
                'quantity' => 3,
                'unit_cost' => 50.00,
                'comment' => 'Late item',
            ]);
        } catch (DomainException $e) {
            $this->assertCount(1, $receipt->fresh()->items);
            $this->assertSame('posted', $receipt->fresh()->status);
            $this->assertEquals($firstStock->quantity, $firstStock->fresh()->quantity);
            $this->assertDatabaseMissing('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $secondProduct->id,
            ]);

            throw $e;
        }
    }

    public function test_cannot_post_receipt_twice(): void
    {
        $this->expectException(DomainException::class);

        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(ReceiptService::class);

        $receipt = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'RCPT-00000001',
            'receipt_date' => '2026-07-27',
            'comment' => 'Incoming goods',
        ]);

        $service->addItem($receipt, [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 120.50,
            'comment' => 'Test item',
        ]);

        $service->post($receipt);

        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'posted',
        ]);

        $stock = Stock::where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(10, $stock->quantity);

        try {
            $service->post($receipt->fresh());
        } catch (DomainException $e) {
            $this->assertDatabaseHas('receipts', [
                'id' => $receipt->id,
                'status' => 'posted',
            ]);

            $stock->refresh();

            $this->assertEquals(10, $stock->quantity);

            $this->assertDatabaseCount('stocks', 1);

            throw $e;
        }
    }
}
