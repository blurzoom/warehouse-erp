<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_belongs_to_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create();
        $receipt = Receipt::factory()->for($warehouse)->create();

        $this->assertInstanceOf(BelongsTo::class, $receipt->warehouse());
        $this->assertTrue($receipt->warehouse->is($warehouse));
    }

    public function test_receipt_has_many_receipt_items(): void
    {
        $receipt = Receipt::factory()->create();
        $product = Product::factory()->create();
        $receiptItem1 = ReceiptItem::factory()->for($receipt)->for($product)->create();
        $receiptItem2 = ReceiptItem::factory()->for($receipt)->for($product)->create();

        $receiptItems = $receipt->items;

        $this->assertInstanceOf(HasMany::class, $receipt->items());
        $this->assertCount(2, $receiptItems);
        $this->assertTrue($receiptItems->contains($receiptItem1));
        $this->assertTrue($receiptItems->contains($receiptItem2));
    }

    public function test_receipt_item_belongs_to_receipt(): void
    {
        $receipt = Receipt::factory()->create();
        $product = Product::factory()->create();
        $receiptItem = ReceiptItem::factory()->for($receipt)->for($product)->create();

        $this->assertInstanceOf(BelongsTo::class, $receiptItem->receipt());
        $this->assertTrue($receiptItem->receipt->is($receipt));
    }

    public function test_receipt_item_belongs_to_product(): void
    {
        $receipt = Receipt::factory()->create();
        $product = Product::factory()->create();
        $receiptItem = ReceiptItem::factory()->for($receipt)->for($product)->create();

        $this->assertInstanceOf(BelongsTo::class, $receiptItem->product());
        $this->assertTrue($receiptItem->product->is($product));
    }
}
