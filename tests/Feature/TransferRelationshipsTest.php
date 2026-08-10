<?php

namespace Tests\Feature;

use App\Enums\TransferStatus;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransferRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_status_has_draft_and_posted_values(): void
    {
        $this->assertSame('draft', TransferStatus::Draft->value);
        $this->assertSame('posted', TransferStatus::Posted->value);
    }

    public function test_transfer_factory_creates_a_valid_transfer(): void
    {
        $transfer = Transfer::factory()->create();

        $this->assertModelExists($transfer);
        $this->assertNotNull($transfer->number);
        $this->assertNotNull($transfer->transfer_date);
        $this->assertNotSame($transfer->from_warehouse_id, $transfer->to_warehouse_id);
    }

    public function test_transfer_status_is_cast_to_transfer_status(): void
    {
        $transfer = Transfer::factory()->create([
            'status' => TransferStatus::Posted,
        ]);

        $this->assertSame(TransferStatus::Posted, $transfer->status);
    }

    public function test_transfer_belongs_to_source_warehouse(): void
    {
        $sourceWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($sourceWarehouse, 'fromWarehouse')
            ->create();

        $this->assertInstanceOf(BelongsTo::class, $transfer->fromWarehouse());
        $this->assertTrue($transfer->fromWarehouse->is($sourceWarehouse));
    }

    public function test_transfer_belongs_to_destination_warehouse(): void
    {
        $destinationWarehouse = Warehouse::factory()->create();
        $transfer = Transfer::factory()
            ->for($destinationWarehouse, 'toWarehouse')
            ->create();

        $this->assertInstanceOf(BelongsTo::class, $transfer->toWarehouse());
        $this->assertTrue($transfer->toWarehouse->is($destinationWarehouse));
    }

    public function test_transfer_has_many_transfer_items(): void
    {
        $transfer = Transfer::factory()->create();
        $firstItem = TransferItem::factory()->for($transfer)->create();
        $secondItem = TransferItem::factory()->for($transfer)->create();

        $this->assertInstanceOf(HasMany::class, $transfer->items());
        $this->assertCount(2, $transfer->items);
        $this->assertTrue($transfer->items->contains($firstItem));
        $this->assertTrue($transfer->items->contains($secondItem));
    }

    public function test_transfer_item_factory_creates_a_valid_transfer_item(): void
    {
        $item = TransferItem::factory()->create();

        $this->assertModelExists($item);
        $this->assertNotNull($item->transfer_id);
        $this->assertNotNull($item->product_id);
        $this->assertGreaterThan(0, (float) $item->quantity);
    }

    public function test_transfer_item_belongs_to_transfer(): void
    {
        $transfer = Transfer::factory()->create();
        $item = TransferItem::factory()->for($transfer)->create();

        $this->assertInstanceOf(BelongsTo::class, $item->transfer());
        $this->assertTrue($item->transfer->is($transfer));
    }

    public function test_transfer_item_belongs_to_product(): void
    {
        $product = Product::factory()->create();
        $item = TransferItem::factory()->for($product)->create();

        $this->assertInstanceOf(BelongsTo::class, $item->product());
        $this->assertTrue($item->product->is($product));
    }

    public function test_transfer_item_quantity_is_cast_to_three_decimal_string(): void
    {
        $item = TransferItem::factory()->create([
            'quantity' => 12.345,
        ]);

        $this->assertSame('12.345', $item->quantity);
    }
}
