<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\IssueService;
use App\Services\ReceiptService;
use App\Services\StockService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_posting_receipt_creates_a_positive_movement_with_resulting_balance(): void
    {
        [$receipt, $product] = $this->createReceiptWithItems([10]);

        app(ReceiptService::class)->post($receipt);

        $movement = StockMovement::firstOrFail();
        $stock = Stock::firstOrFail();

        $this->assertSame(StockMovementType::Receipt, $movement->type);
        $this->assertSame('10.000', $movement->quantity);
        $this->assertEquals($stock->quantity, $movement->balance_after);
        $this->assertTrue($movement->source->is($receipt));
        $this->assertSame($product->id, $movement->product_id);
    }

    public function test_posting_receipt_creates_one_movement_per_item_and_cannot_duplicate_them(): void
    {
        [$receipt] = $this->createReceiptWithItems([10, 5]);
        $service = app(ReceiptService::class);

        $service->post($receipt);

        $this->assertDatabaseCount('stock_movements', 2);

        try {
            $service->post($receipt->fresh());
            $this->fail('Posting a receipt twice should not be allowed.');
        } catch (DomainException) {
            $this->assertDatabaseCount('stock_movements', 2);
        }
    }

    public function test_receipt_posting_rolls_back_stock_and_movements_when_movement_creation_fails(): void
    {
        [$receipt] = $this->createReceiptWithItems([10]);
        StockMovement::creating(static function (): never {
            throw new DomainException('Movement creation failed');
        });

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Movement creation failed');

        try {
            app(ReceiptService::class)->post($receipt);
        } catch (DomainException $exception) {
            $this->assertDatabaseCount('stocks', 0);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame('draft', $receipt->fresh()->status);

            throw $exception;
        }
    }

    public function test_posting_issue_creates_a_negative_movement_with_resulting_balance(): void
    {
        [$issue, $product] = $this->createIssueWithItems([4], 10);

        app(IssueService::class)->post($issue);

        $movement = StockMovement::firstOrFail();
        $stock = Stock::firstOrFail();

        $this->assertSame(StockMovementType::Issue, $movement->type);
        $this->assertSame('-4.000', $movement->quantity);
        $this->assertEquals($stock->quantity, $movement->balance_after);
        $this->assertTrue($movement->source->is($issue));
        $this->assertSame($product->id, $movement->product_id);
    }

    public function test_issue_posting_respects_reserved_stock_without_creating_a_movement(): void
    {
        [$issue] = $this->createIssueWithItems([6], 10, 5);

        $this->expectException(DomainException::class);

        try {
            app(IssueService::class)->post($issue);
        } catch (DomainException $exception) {
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame('draft', $issue->fresh()->status);

            throw $exception;
        }
    }

    public function test_posting_issue_creates_one_movement_per_item_and_cannot_duplicate_them(): void
    {
        [$issue] = $this->createIssueWithItems([4, 3], 10);
        $service = app(IssueService::class);

        $service->post($issue);

        $this->assertDatabaseCount('stock_movements', 2);

        try {
            $service->post($issue->fresh());
            $this->fail('Posting an issue twice should not be allowed.');
        } catch (DomainException) {
            $this->assertDatabaseCount('stock_movements', 2);
        }
    }

    public function test_issue_posting_rolls_back_stock_and_movements_when_movement_creation_fails(): void
    {
        [$issue, $product] = $this->createIssueWithItems([4], 10);
        StockMovement::creating(static function (): never {
            throw new DomainException('Movement creation failed');
        });

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Movement creation failed');

        try {
            app(IssueService::class)->post($issue);
        } catch (DomainException $exception) {
            $stock = Stock::where('warehouse_id', $issue->warehouse_id)
                ->where('product_id', $product->id)
                ->firstOrFail();

            $this->assertEquals(10, $stock->quantity);
            $this->assertDatabaseCount('stock_movements', 0);
            $this->assertSame('draft', $issue->fresh()->status);

            throw $exception;
        }
    }

    /**
     * @param  array<int, float|int>  $quantities
     * @return array{Receipt, Product}
     */
    private function createReceiptWithItems(array $quantities): array
    {
        $warehouse = Warehouse::factory()->create();
        $receipt = Receipt::factory()->for($warehouse)->create();
        $firstProduct = Product::factory()->create();

        foreach ($quantities as $index => $quantity) {
            ReceiptItem::factory()
                ->for($receipt)
                ->for($index === 0 ? $firstProduct : Product::factory())
                ->create(['quantity' => $quantity]);
        }

        return [$receipt, $firstProduct];
    }

    /**
     * @param  array<int, float|int>  $quantities
     * @return array{Issue, Product}
     */
    private function createIssueWithItems(array $quantities, float $stockQuantity, float $reservedQuantity = 0): array
    {
        $warehouse = Warehouse::factory()->create();
        $issue = Issue::factory()->for($warehouse)->create();
        $firstProduct = Product::factory()->create();
        $stockService = app(StockService::class);

        foreach ($quantities as $index => $quantity) {
            $product = $index === 0 ? $firstProduct : Product::factory()->create();
            $stockService->increase($warehouse, $product, $stockQuantity);
            IssueItem::factory()->for($issue)->for($product)->create(['quantity' => $quantity]);
        }

        if ($reservedQuantity > 0) {
            $stockService->reserve($warehouse, $firstProduct, $reservedQuantity);
        }

        return [$issue, $firstProduct];
    }
}
