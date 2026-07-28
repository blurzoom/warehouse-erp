<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_receipt(): void
    {
        $warehouse = Warehouse::factory()->create();

        $receipt = Receipt::factory()
            ->for($warehouse)
            ->create();

        $this->assertDatabaseHas('receipts', [
            'number' => $receipt->number,
            'warehouse_id' => $warehouse->id,
            'status' => 'draft',
        ]);
    }

    public function test_receipt_requires_existing_warehouse(): void
    {
        $this->expectException(QueryException::class);

        Receipt::factory()->create([
            'warehouse_id' => 999999,
        ]);
    }

    public function test_receipt_number_must_be_unique(): void
    {
        $receipt = Receipt::factory()->create();

        $this->expectException(QueryException::class);

        Receipt::factory()->create([
            'number' => $receipt->number,
        ]);
    }
}
