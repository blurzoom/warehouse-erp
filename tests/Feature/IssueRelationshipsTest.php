<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\IssueItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_belongs_to_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $issue = Issue::factory()->for($warehouse)->create();

        $this->assertInstanceOf(BelongsTo::class, $issue->warehouse());
        $this->assertTrue($issue->warehouse->is($warehouse));
    }

    public function test_warehouse_has_many_issues(): void
    {
        $warehouse = Warehouse::factory()->create();
        $firstIssue = Issue::factory()->for($warehouse)->create();
        $secondIssue = Issue::factory()->for($warehouse)->create();

        $this->assertInstanceOf(HasMany::class, $warehouse->issues());
        $this->assertCount(2, $warehouse->issues);
        $this->assertTrue($warehouse->issues->contains($firstIssue));
        $this->assertTrue($warehouse->issues->contains($secondIssue));
    }

    public function test_issue_has_many_issue_items(): void
    {
        $issue = Issue::factory()->create();
        $product = Product::factory()->create();
        $firstItem = IssueItem::factory()->for($issue)->for($product)->create();
        $secondItem = IssueItem::factory()->for($issue)->for($product)->create();

        $this->assertInstanceOf(HasMany::class, $issue->items());
        $this->assertCount(2, $issue->items);
        $this->assertTrue($issue->items->contains($firstItem));
        $this->assertTrue($issue->items->contains($secondItem));
    }

    public function test_issue_item_belongs_to_issue(): void
    {
        $issue = Issue::factory()->create();
        $item = IssueItem::factory()->for($issue)->create();

        $this->assertInstanceOf(BelongsTo::class, $item->issue());
        $this->assertTrue($item->issue->is($issue));
    }

    public function test_issue_item_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $item = IssueItem::factory()->for($product)->create();

        $this->assertInstanceOf(BelongsTo::class, $item->product());
        $this->assertTrue($item->product->is($product));
    }
}
