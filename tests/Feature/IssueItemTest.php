<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class IssueItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_issue_item_with_three_decimal_quantity(): void
    {
        $issue = Issue::factory()->create();
        $product = Product::factory()->create();

        $item = IssueItem::factory()
            ->for($issue)
            ->for($product)
            ->create([
                'quantity' => 12.345,
            ]);

        $this->assertDatabaseHas('issue_items', [
            'issue_id' => $issue->id,
            'product_id' => $product->id,
            'quantity' => 12.345,
        ]);
        $this->assertSame('12.345', $item->quantity);
        $this->assertFalse(Schema::hasColumn('issue_items', 'unit_cost'));
    }

    public function test_issue_item_requires_existing_issue(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        IssueItem::factory()->create([
            'issue_id' => 999999,
            'product_id' => $product->id,
        ]);
    }

    public function test_issue_item_requires_existing_product(): void
    {
        $issue = Issue::factory()->create();

        $this->expectException(QueryException::class);

        IssueItem::factory()->create([
            'issue_id' => $issue->id,
            'product_id' => 999999,
        ]);
    }

    public function test_product_deletion_is_restricted_when_it_has_issue_items(): void
    {
        $item = IssueItem::factory()->create();

        $this->expectException(QueryException::class);

        $item->product->delete();
    }
}
