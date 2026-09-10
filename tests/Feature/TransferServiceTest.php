<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Warehouse;
use App\Services\StockService;
use App\Services\TransferService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;
use Throwable;

class TransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_transfer_always_uses_draft_status(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $number = 'TRF-00000001';
        $transferDate = '2026-08-14';

        $transfer = app(TransferService::class)->create([
            'number' => $number,
            'transfer_date' => $transferDate,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'status' => TransferStatus::Posted,
        ]);

        $this->assertInstanceOf(Transfer::class, $transfer);
        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'number' => $number,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'status' => TransferStatus::Draft->value,
        ]);
        $freshTransfer = $transfer->fresh();

        $this->assertSame($transferDate, $freshTransfer->transfer_date->toDateString());
        $this->assertCount(0, $transfer->items);
        $this->assertDatabaseCount('transfer_items', 0);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_create_transfer_persists_optional_comment(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $number = 'TRF-00000001';
        $transferDate = '2026-08-14';
        $comment = 'Transfer goods to the destination warehouse';

        $transfer = app(TransferService::class)->create([
            'number' => $number,
            'transfer_date' => $transferDate,
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'comment' => $comment,
        ]);

        $this->assertInstanceOf(Transfer::class, $transfer);
        $freshTransfer = $transfer->fresh();

        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame($comment, $freshTransfer->comment);
    }

    public function test_item_can_be_added_to_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(TransferService::class);
        $transfer = $service->create([
            'number' => 'TRF-00000001',
            'transfer_date' => '2026-08-14',
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
        ]);

        $item = $service->addItem($transfer, [
            'product_id' => $product->id,
            'quantity' => 4.0,
        ]);

        $this->assertInstanceOf(TransferItem::class, $item);
        $this->assertSame($transfer->id, $item->transfer_id);
        $this->assertSame($product->id, $item->product_id);
        $this->assertEquals(4.0, $item->quantity);
        $this->assertDatabaseHas('transfer_items', [
            'id' => $item->id,
            'transfer_id' => $transfer->id,
            'product_id' => $product->id,
            'quantity' => 4.0,
        ]);
        $this->assertCount(1, $transfer->fresh()->items);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_add_item_persists_optional_comment(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(TransferService::class);
        $transfer = $service->create([
            'number' => 'TRF-00000001',
            'transfer_date' => '2026-08-14',
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
        ]);

        $item = $service->addItem($transfer, [
            'product_id' => $product->id,
            'quantity' => 4.0,
            'comment' => 'Handle with care',
        ]);

        $freshItem = $item->fresh();

        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('Handle with care', $freshItem->comment);
    }

    public function test_same_product_cannot_be_added_twice_to_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(TransferService::class);
        $transfer = $service->create([
            'number' => 'TRF-00000001',
            'transfer_date' => '2026-08-18',
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
        ]);

        $this->assertSame(TransferStatus::Draft, $transfer->status);

        $item = $service->addItem($transfer, [
            'product_id' => $product->id,
            'quantity' => 4.0,
        ]);

        $this->assertModelExists($item);
        $this->assertSame($transfer->id, $item->transfer_id);
        $this->assertSame($product->id, $item->product_id);

        $caughtException = null;

        try {
            $service->addItem($transfer, [
                'product_id' => $product->id,
                'quantity' => 2.0,
            ]);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }

        $matchingItemCount = TransferItem::query()
            ->where('transfer_id', $transfer->id)
            ->where('product_id', $product->id)
            ->count();

        $this->assertSame(1, $matchingItemCount);
        $this->assertInstanceOf(DomainException::class, $caughtException);
    }

    public function test_item_with_zero_quantity_cannot_be_added_to_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $service = app(TransferService::class);
        $transfer = $service->create([
            'number' => 'TRF-00000001',
            'transfer_date' => '2026-08-19',
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
        ]);

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertDatabaseCount('transfer_items', 0);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Transfer quantity must be greater than zero');

        try {
            $service->addItem($transfer, [
                'product_id' => $product->id,
                'quantity' => 0.0,
            ]);
        } catch (DomainException $exception) {
            $this->assertDatabaseCount('transfer_items', 0);
            $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
            $this->assertDatabaseCount('stocks', 0);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_item_can_be_updated_on_a_draft_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Draft,
        ]);
        $product = Product::factory()->create();
        $item = TransferItem::factory()
            ->for($transfer)
            ->for($product)
            ->create(['quantity' => 4.0]);

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertModelExists($item);

        $updatedItem = app(TransferService::class)->updateItem($item, [
            'quantity' => 7.0,
        ]);

        $freshItem = $item->fresh();

        $this->assertSame($item->id, $updatedItem->id);
        $this->assertSame('7.000', $freshItem->quantity);
        $this->assertSame($product->id, $freshItem->product_id);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('transfer_items', 1);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_item_comment_can_be_updated_on_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create(['status' => TransferStatus::Draft]);
        $product = Product::factory()->create();
        $item = TransferItem::factory()
            ->for($transfer)
            ->for($product)
            ->create([
                'quantity' => 4,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->updateItem($item, [
            'quantity' => 4,
            'comment' => 'Updated comment',
        ]);

        $freshItem = $item->fresh();

        $this->assertSame('4.000', $freshItem->quantity);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('Updated comment', $freshItem->comment);
    }

    public function test_item_comment_can_be_updated_without_quantity_on_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create(['status' => TransferStatus::Draft]);
        $product = Product::factory()->create();
        $item = TransferItem::factory()
            ->for($transfer)
            ->for($product)
            ->create([
                'quantity' => 4,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->updateItem($item, [
            'comment' => 'Updated comment',
        ]);

        $freshItem = $item->fresh();

        $this->assertSame('Updated comment', $freshItem->comment);
        $this->assertSame('4.000', $freshItem->quantity);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_item_comment_can_be_cleared_on_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create(['status' => TransferStatus::Draft]);
        $product = Product::factory()->create();
        $item = TransferItem::factory()
            ->for($transfer)
            ->for($product)
            ->create([
                'quantity' => 4,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->updateItem($item, [
            'comment' => null,
        ]);

        $freshItem = $item->fresh();

        $this->assertNull($freshItem->comment);
        $this->assertSame('4.000', $freshItem->quantity);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_item_quantity_cannot_be_updated_to_zero_on_a_draft_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Draft,
        ]);
        $item = TransferItem::factory()
            ->for($transfer)
            ->create(['quantity' => 4.0]);

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertModelExists($item);
        $this->assertSame('4.000', $item->fresh()->quantity);

        $caughtException = null;

        try {
            app(TransferService::class)->updateItem($item, [
                'quantity' => 0.0,
            ]);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }

        $freshItem = $item->fresh();

        $this->assertSame('4.000', $freshItem->quantity);
        $this->assertModelExists($freshItem);
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertDatabaseCount('transfer_items', 1);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertInstanceOf(DomainException::class, $caughtException);
    }

    public function test_item_can_be_removed_from_a_draft_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Draft,
        ]);
        $itemToRemove = TransferItem::factory()->for($transfer)->create();
        $remainingItem = TransferItem::factory()->for($transfer)->create();

        $this->assertSame(TransferStatus::Draft, $transfer->status);
        $this->assertModelExists($itemToRemove);
        $this->assertModelExists($remainingItem);
        $this->assertTrue($transfer->fresh()->items->contains($itemToRemove));
        $this->assertCount(2, $transfer->fresh()->items);

        app(TransferService::class)->removeItem($itemToRemove);

        $freshTransfer = $transfer->fresh();

        $this->assertModelMissing($itemToRemove);
        $this->assertModelExists($remainingItem);
        $this->assertSame($remainingItem->id, $freshTransfer->items->sole()->id);
        $this->assertModelExists($freshTransfer);
        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
        $this->assertDatabaseCount('transfer_items', 1);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_item_cannot_be_removed_from_a_posted_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
        ]);
        $item = TransferItem::factory()->for($transfer)->create();

        $this->assertSame(TransferStatus::Posted, $transfer->status);
        $this->assertModelExists($item);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot modify a posted transfer');

        try {
            app(TransferService::class)->removeItem($item);
        } catch (DomainException $exception) {
            $this->assertModelExists($item);
            $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
            $this->assertDatabaseCount('transfer_items', 1);
            $this->assertDatabaseCount('stocks', 0);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_item_cannot_be_updated_on_a_posted_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
        ]);
        $item = TransferItem::factory()
            ->for($transfer)
            ->create(['quantity' => 4.0]);

        $this->assertSame(TransferStatus::Posted, $transfer->status);
        $this->assertModelExists($item);
        $this->assertSame('4.000', $item->fresh()->quantity);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot modify a posted transfer');

        try {
            app(TransferService::class)->updateItem($item, [
                'quantity' => 7.0,
            ]);
        } catch (DomainException $exception) {
            $freshItem = $item->fresh();

            $this->assertSame('4.000', $freshItem->quantity);
            $this->assertModelExists($freshItem);
            $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
            $this->assertDatabaseCount('transfer_items', 1);
            $this->assertDatabaseCount('stocks', 0);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_item_comment_cannot_be_updated_on_a_posted_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
        ]);
        $item = TransferItem::factory()
            ->for($transfer)
            ->create([
                'quantity' => 4.0,
                'comment' => 'Original comment',
            ]);

        $caughtException = null;

        try {
            app(TransferService::class)->updateItem($item, [
                'comment' => 'Updated comment',
            ]);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }

        $freshItem = $item->fresh();

        $this->assertSame('Original comment', $freshItem->comment);
        $this->assertSame('4.000', $freshItem->quantity);
        $this->assertInstanceOf(DomainException::class, $caughtException);
        $this->assertSame('Cannot modify a posted transfer', $caughtException?->getMessage());
    }

    public function test_item_cannot_be_added_to_posted_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
        ]);
        $product = Product::factory()->create();
        $service = app(TransferService::class);

        $this->assertCount(0, $transfer->items);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Cannot modify a posted transfer');

        try {
            $service->addItem($transfer, [
                'product_id' => $product->id,
                'quantity' => 4.0,
            ]);
        } catch (DomainException $exception) {
            $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
            $this->assertDatabaseCount('transfer_items', 0);
            $this->assertDatabaseCount('stocks', 0);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_posting_a_draft_transfer_moves_stock_creates_paired_movements_and_marks_it_as_posted(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $firstProduct, $secondProduct] = $this->createTransferWithItems([
            [10, 4, 3],
            [8, 2, 5],
        ]);

        app(TransferService::class)->post($transfer);

        $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
        $this->assertStockQuantity($sourceWarehouse, $firstProduct, 6);
        $this->assertStockQuantity($destinationWarehouse, $firstProduct, 7);
        $this->assertStockQuantity($sourceWarehouse, $secondProduct, 6);
        $this->assertStockQuantity($destinationWarehouse, $secondProduct, 7);

        $this->assertTransferMovement($transfer, $sourceWarehouse, $firstProduct, StockMovementType::TransferOut, -4, 6);
        $this->assertTransferMovement($transfer, $destinationWarehouse, $firstProduct, StockMovementType::TransferIn, 4, 7);
        $this->assertTransferMovement($transfer, $sourceWarehouse, $secondProduct, StockMovementType::TransferOut, -2, 6);
        $this->assertTransferMovement($transfer, $destinationWarehouse, $secondProduct, StockMovementType::TransferIn, 2, 7);
        $this->assertDatabaseCount('stock_movements', 4);
    }

    public function test_posting_delegates_all_item_quantities_to_one_batch_stock_transfer(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $firstProduct, $secondProduct] = $this->createTransferWithItems([
            [10, 4.0, 3],
            [8, 2.0, 5],
        ]);
        $stockService = Mockery::mock(StockService::class)->makePartial();
        $this->app->instance(StockService::class, $stockService);
        $service = app(TransferService::class);

        $service->post($transfer);

        $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
        $this->assertStockQuantity($sourceWarehouse, $firstProduct, 6);
        $this->assertStockQuantity($destinationWarehouse, $firstProduct, 7);
        $this->assertStockQuantity($sourceWarehouse, $secondProduct, 6);
        $this->assertStockQuantity($destinationWarehouse, $secondProduct, 7);

        $this->assertTransferMovement($transfer, $sourceWarehouse, $firstProduct, StockMovementType::TransferOut, -4, 6);
        $this->assertTransferMovement($transfer, $destinationWarehouse, $firstProduct, StockMovementType::TransferIn, 4, 7);
        $this->assertTransferMovement($transfer, $sourceWarehouse, $secondProduct, StockMovementType::TransferOut, -2, 6);
        $this->assertTransferMovement($transfer, $destinationWarehouse, $secondProduct, StockMovementType::TransferIn, 2, 7);
        $this->assertDatabaseCount('stock_movements', 4);

        $expectedQuantitiesByProductId = [
            $firstProduct->id => 4.0,
            $secondProduct->id => 2.0,
        ];
        ksort($expectedQuantitiesByProductId, SORT_NUMERIC);

        $stockService->shouldHaveReceived('transfer')
            ->once()
            ->withArgs(function (
                Warehouse $actualSourceWarehouse,
                Warehouse $actualDestinationWarehouse,
                array $actualQuantitiesByProductId,
            ) use ($sourceWarehouse, $destinationWarehouse, $expectedQuantitiesByProductId): bool {
                ksort($actualQuantitiesByProductId, SORT_NUMERIC);

                return $actualSourceWarehouse->getKey() === $sourceWarehouse->getKey()
                    && $actualDestinationWarehouse->getKey() === $destinationWarehouse->getKey()
                    && $actualQuantitiesByProductId === $expectedQuantitiesByProductId;
            });
    }

    public function test_a_transfer_cannot_be_posted_twice(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $product] = $this->createTransferWithItems([
            [10, 4, 3],
        ]);
        $service = app(TransferService::class);

        $service->post($transfer);

        $this->expectException(DomainException::class);

        try {
            $service->post($transfer->fresh());
        } catch (DomainException $exception) {
            $this->assertSame(TransferStatus::Posted, $transfer->fresh()->status);
            $this->assertStockQuantity($sourceWarehouse, $product, 6);
            $this->assertStockQuantity($destinationWarehouse, $product, 7);
            $this->assertDatabaseCount('stock_movements', 2);

            throw $exception;
        }
    }

    public function test_an_empty_transfer_cannot_be_posted(): void
    {
        $transfer = Transfer::factory()->create();

        $this->expectException(DomainException::class);

        try {
            app(TransferService::class)->post($transfer);
        } catch (DomainException $exception) {
            $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_posting_fails_when_the_source_has_insufficient_available_stock(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $product] = $this->createTransferWithItems([
            [10, 6, 3, 5],
        ]);

        $this->expectException(DomainException::class);

        try {
            app(TransferService::class)->post($transfer);
        } catch (DomainException $exception) {
            $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
            $this->assertStockQuantity($sourceWarehouse, $product, 10);
            $this->assertStockQuantity($destinationWarehouse, $product, 3);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_posting_rolls_back_all_stock_movements_and_status_when_a_later_item_fails(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $firstProduct, $secondProduct] = $this->createTransferWithItems([
            [10, 4, 3],
            [2, 3, 5],
        ]);

        $this->expectException(DomainException::class);

        try {
            app(TransferService::class)->post($transfer);
        } catch (DomainException $exception) {
            $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
            $this->assertStockQuantity($sourceWarehouse, $firstProduct, 10);
            $this->assertStockQuantity($destinationWarehouse, $firstProduct, 3);
            $this->assertStockQuantity($sourceWarehouse, $secondProduct, 2);
            $this->assertStockQuantity($destinationWarehouse, $secondProduct, 5);
            $this->assertDatabaseCount('stock_movements', 0);

            throw $exception;
        }
    }

    public function test_posting_rolls_back_stock_status_and_movements_when_movement_creation_fails(): void
    {
        [$transfer, $sourceWarehouse, $destinationWarehouse, $product] = $this->createTransferWithItems([
            [10, 4, 3],
        ]);
        StockMovement::creating(static function (): never {
            throw new DomainException('Movement creation failed');
        });
        $caughtException = null;

        try {
            app(TransferService::class)->post($transfer);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }

        $this->assertInstanceOf(DomainException::class, $caughtException);
        $this->assertSame('Movement creation failed', $caughtException->getMessage());
        $this->assertSame(TransferStatus::Draft, $transfer->fresh()->status);
        $this->assertStockQuantity($sourceWarehouse, $product, 10);
        $this->assertStockQuantity($destinationWarehouse, $product, 3);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_header_can_be_updated_on_a_draft_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Draft,
            'transfer_date' => '2026-08-23',
        ]);
        $newSourceWarehouse = Warehouse::factory()->create();
        $newDestinationWarehouse = Warehouse::factory()->create();

        app(TransferService::class)->update($transfer, [
            'number' => 'TRF-UPDATED',
            'transfer_date' => '2026-08-24',
            'from_warehouse_id' => $newSourceWarehouse->id,
            'to_warehouse_id' => $newDestinationWarehouse->id,
        ]);

        $freshTransfer = $transfer->fresh();

        $this->assertSame('TRF-UPDATED', $freshTransfer->number);
        $this->assertSame('2026-08-24', $freshTransfer->transfer_date->toDateString());
        $this->assertSame($newSourceWarehouse->id, $freshTransfer->from_warehouse_id);
        $this->assertSame($newDestinationWarehouse->id, $freshTransfer->to_warehouse_id);
        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
    }

    public function test_comment_can_be_updated_on_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create([
                'status' => TransferStatus::Draft,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->update($transfer, [
            'comment' => 'Updated comment',
        ]);

        $freshTransfer = $transfer->fresh();

        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame('Updated comment', $freshTransfer->comment);
    }

    public function test_comment_can_be_cleared_on_a_draft_transfer(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create([
                'status' => TransferStatus::Draft,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->update($transfer, [
            'comment' => null,
        ]);

        $freshTransfer = $transfer->fresh();

        $this->assertNull($freshTransfer->comment);
        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
        $this->assertDatabaseCount('stocks', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_comment_is_preserved_when_omitted_from_draft_update(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create([
                'status' => TransferStatus::Draft,
                'comment' => 'Original comment',
            ]);

        app(TransferService::class)->update($transfer, [
            'number' => 'TRF-UPDATED',
        ]);

        $freshTransfer = $transfer->fresh();

        $this->assertSame('TRF-UPDATED', $freshTransfer->number);
        $this->assertSame('Original comment', $freshTransfer->comment);
        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
    }

    public function test_draft_transfer_cannot_be_updated_to_use_same_source_and_destination_warehouse(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create([
                'status' => TransferStatus::Draft,
            ]);

        $caughtException = null;

        try {
            app(TransferService::class)->update($transfer, [
                'to_warehouse_id' => $sourceWarehouse->id,
            ]);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }

        $freshTransfer = $transfer->fresh();

        $this->assertSame($sourceWarehouse->id, $freshTransfer->from_warehouse_id);
        $this->assertSame($destinationWarehouse->id, $freshTransfer->to_warehouse_id);
        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
        $this->assertInstanceOf(DomainException::class, $caughtException);
        $this->assertSame(
            'Source and destination warehouses must be different',
            $caughtException?->getMessage(),
        );
    }

    public function test_header_cannot_be_updated_on_a_posted_transfer(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
            'transfer_date' => '2026-08-23',
        ]);
        $caughtException = null;

        try {
            app(TransferService::class)->update($transfer, [
                'transfer_date' => '2026-08-24',
            ]);
        } catch (Throwable $exception) {
            $caughtException = $exception;
        }
        $freshTransfer = $transfer->fresh();
        $this->assertSame(
            '2026-08-23',
            $freshTransfer->transfer_date->toDateString(),
        );
        $this->assertSame(TransferStatus::Posted, $freshTransfer->status);
        $this->assertInstanceOf(DomainException::class, $caughtException);
        $this->assertSame(
            'Cannot modify a posted transfer',
            $caughtException?->getMessage(),
        );
    }

    public function test_update_cannot_change_transfer_status_directly(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Draft,
        ]);

        app(TransferService::class)->update($transfer, [
            'status' => TransferStatus::Posted,
        ]);

        $freshTransfer = $transfer->fresh();

        $this->assertSame(TransferStatus::Draft, $freshTransfer->status);
    }

    /**
     * @param  array<int, array{0: float|int, 1: float|int, 2: float|int, 3?: float|int}>  $items
     * @return array{Transfer, Warehouse, Warehouse, Product, Product}
     */
    private function createTransferWithItems(array $items): array
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->for($destinationWarehouse, 'toWarehouse')
            ->create();
        $products = [];

        foreach ($items as $item) {
            [$sourceQuantity, $transferQuantity, $destinationQuantity] = $item;
            $reservedQuantity = $item[3] ?? 0;
            $product = Product::factory()->create();
            $products[] = $product;
            Stock::factory()->for($sourceWarehouse)->for($product)->create([
                'quantity' => $sourceQuantity,
                'reserved' => $reservedQuantity,
            ]);
            Stock::factory()->for($destinationWarehouse)->for($product)->create([
                'quantity' => $destinationQuantity,
                'reserved' => 0,
            ]);
            TransferItem::factory()->for($transfer)->for($product)->create([
                'quantity' => $transferQuantity,
            ]);
        }

        return [$transfer, $sourceWarehouse, $destinationWarehouse, $products[0], $products[1] ?? $products[0]];
    }

    private function assertStockQuantity(Warehouse $warehouse, Product $product, float|int $quantity): void
    {
        $stock = Stock::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();

        $this->assertEquals($quantity, $stock->quantity);
    }

    private function assertTransferMovement(
        Transfer $transfer,
        Warehouse $warehouse,
        Product $product,
        StockMovementType $type,
        float|int $quantity,
        float|int $balanceAfter,
    ): void {
        $movement = StockMovement::query()
            ->where('warehouse_id', $warehouse->id)
            ->where('product_id', $product->id)
            ->where('type', $type)
            ->firstOrFail();

        $this->assertTrue($movement->source->is($transfer));
        $this->assertEquals($quantity, $movement->quantity);
        $this->assertEquals($balanceAfter, $movement->balance_after);
    }
}
