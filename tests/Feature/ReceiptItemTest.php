<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_receipt_item(): void
    {
        $receipt = Receipt::factory()->create();
        $product = Product::factory()->create();

        $receiptItem = ReceiptItem::factory()
            ->for($receipt)
            ->for($product)
            ->create();

        $this->assertDatabaseHas('receipt_items', [
            'receipt_id' => $receipt->id,
            'product_id' => $product->id,
            'quantity' => $receiptItem->quantity,
            'unit_cost' => $receiptItem->unit_cost,
        ]);
    }

    public function test_receipt_item_requires_existing_receipt(): void
    {
        $product = Product::factory()->create();

        $this->expectException(QueryException::class);

        ReceiptItem::factory()->create([
            'receipt_id' => 999999,
            'product_id' => $product->id,
        ]);
    }

    public function test_receipt_item_requires_existing_product(): void
    {
        $receipt = Receipt::factory()->create();

        $this->expectException(QueryException::class);

        ReceiptItem::factory()->create([
            'receipt_id' => $receipt->id,
            'product_id' => 999999,
        ]);
    }
}
