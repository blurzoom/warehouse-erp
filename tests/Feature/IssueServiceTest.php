<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use App\Services\IssueService;
use App\Services\StockService;
use BadMethodCallException;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_issue_always_uses_draft_status(): void
    {
        $warehouse = Warehouse::factory()->create();
        $service = app(IssueService::class);

        $issue = $service->create([
            'warehouse_id' => $warehouse->id,
            'number' => 'ISS-00000001',
            'issue_date' => '2026-07-31',
            'status' => 'posted',
            'comment' => 'Outgoing goods',
        ]);

        $this->assertInstanceOf(Issue::class, $issue);
        $this->assertSame('draft', $issue->status);
    }

    public function test_items_can_be_added_updated_and_removed_from_a_draft_issue(): void
    {
        $issue = Issue::factory()->create();
        $product = Product::factory()->create();
        $service = app(IssueService::class);

        $item = $service->addItem($issue, [
            'product_id' => $product->id,
            'quantity' => 5,
            'comment' => 'Initial item',
        ]);

        $updatedItem = $service->updateItem($item, [
            'quantity' => 10,
            'comment' => 'Updated item',
        ]);

        $this->assertSame($item->id, $updatedItem->id);
        $this->assertSame('10.000', $updatedItem->quantity);
        $this->assertSame('Updated item', $updatedItem->comment);

        $service->removeItem($item);

        $this->assertDatabaseMissing('issue_items', ['id' => $item->id]);
    }

    public function test_items_cannot_be_changed_after_posting(): void
    {
        [$issue, $item] = $this->createPostableIssue();
        $service = app(IssueService::class);

        $service->post($issue);

        foreach (['add', 'update', 'remove'] as $operation) {
            try {
                match ($operation) {
                    'add' => $service->addItem($issue->fresh(), [
                        'product_id' => Product::factory()->create()->id,
                        'quantity' => 1,
                    ]),
                    'update' => $service->updateItem($item->fresh(), ['quantity' => 2]),
                    'remove' => $service->removeItem($item->fresh()),
                };
                $this->fail("The {$operation} operation should not be allowed on a posted issue.");
            } catch (DomainException) {
                $this->assertSame('posted', $issue->fresh()->status);
            }
        }
    }

    public function test_cannot_post_empty_issue(): void
    {
        $issue = Issue::factory()->create();
        $service = app(IssueService::class);

        $this->expectException(DomainException::class);

        try {
            $service->post($issue);
        } catch (DomainException $exception) {
            $this->assertSame('draft', $issue->fresh()->status);

            throw $exception;
        }
    }

    public function test_posting_decreases_stock(): void
    {
        [$issue] = $this->createPostableIssue(quantity: 10, stockQuantity: 20);
        $service = app(IssueService::class);

        $service->post($issue);

        $this->assertDatabaseHas('stocks', [
            'warehouse_id' => $issue->warehouse_id,
            'product_id' => $issue->items->first()->product_id,
            'quantity' => 10,
        ]);
        $this->assertSame('posted', $issue->fresh()->status);
    }

    public function test_posting_respects_reserved_stock(): void
    {
        [$issue] = $this->createPostableIssue(quantity: 6, stockQuantity: 10, reservedQuantity: 5);
        $service = app(IssueService::class);

        $this->expectException(DomainException::class);

        try {
            $service->post($issue);
        } catch (DomainException $exception) {
            $stock = Stock::where('warehouse_id', $issue->warehouse_id)
                ->where('product_id', $issue->items->first()->product_id)
                ->firstOrFail();

            $this->assertEquals(10, $stock->quantity);
            $this->assertEquals(5, $stock->reserved);
            $this->assertSame('draft', $issue->fresh()->status);

            throw $exception;
        }
    }

    public function test_insufficient_stock_rolls_back_all_item_decreases(): void
    {
        $warehouse = Warehouse::factory()->create();
        $firstProduct = Product::factory()->create();
        $secondProduct = Product::factory()->create();
        $stockService = app(StockService::class);
        $stockService->increase($warehouse, $firstProduct, 10);
        $stockService->increase($warehouse, $secondProduct, 2);
        $issue = Issue::factory()->for($warehouse)->create();
        IssueItem::factory()->for($issue)->for($firstProduct)->create(['quantity' => 5]);
        IssueItem::factory()->for($issue)->for($secondProduct)->create(['quantity' => 3]);
        $service = app(IssueService::class);

        $this->expectException(DomainException::class);

        try {
            $service->post($issue);
        } catch (DomainException $exception) {
            $this->assertDatabaseHas('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $firstProduct->id,
                'quantity' => 10,
            ]);
            $this->assertDatabaseHas('stocks', [
                'warehouse_id' => $warehouse->id,
                'product_id' => $secondProduct->id,
                'quantity' => 2,
            ]);
            $this->assertSame('draft', $issue->fresh()->status);

            throw $exception;
        }
    }

    public function test_cannot_post_issue_twice(): void
    {
        [$issue, $item] = $this->createPostableIssue();
        $service = app(IssueService::class);

        $service->post($issue);

        $this->expectException(DomainException::class);

        try {
            $service->post($issue->fresh());
        } catch (DomainException $exception) {
            $stock = Stock::where('warehouse_id', $issue->warehouse_id)
                ->where('product_id', $item->product_id)
                ->firstOrFail();

            $this->assertSame('posted', $issue->fresh()->status);
            $this->assertEquals(5, $stock->quantity);

            throw $exception;
        }
    }

    public function test_cancel_is_not_implemented(): void
    {
        $service = app(IssueService::class);

        $this->expectException(BadMethodCallException::class);

        $service->cancel(Issue::factory()->create());
    }

    /**
     * @return array{Issue, IssueItem}
     */
    private function createPostableIssue(
        float $quantity = 5,
        float $stockQuantity = 10,
        float $reservedQuantity = 0,
    ): array {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $stockService = app(StockService::class);
        $stockService->increase($warehouse, $product, $stockQuantity);

        if ($reservedQuantity > 0) {
            $stockService->reserve($warehouse, $product, $reservedQuantity);
        }

        $issue = Issue::factory()->for($warehouse)->create();
        $item = IssueItem::factory()->for($issue)->for($product)->create(['quantity' => $quantity]);

        return [$issue, $item];
    }
}
