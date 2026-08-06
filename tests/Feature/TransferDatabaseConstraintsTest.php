<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TransferDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_can_be_created_with_required_fields_and_defaults_to_draft(): void
    {
        $transferId = $this->insertTransfer();

        $transfer = DB::table('transfers')->find($transferId);

        $this->assertNotNull($transfer);
        $this->assertSame('draft', $transfer->status);
        $this->assertNotNull($transfer->number);
        $this->assertNotNull($transfer->transfer_date);
        $this->assertNotNull($transfer->from_warehouse_id);
        $this->assertNotNull($transfer->to_warehouse_id);
    }

    public function test_transfer_can_be_created_with_posted_status(): void
    {
        $transferId = $this->insertTransfer(['status' => 'posted']);

        $transfer = DB::table('transfers')->find($transferId);

        $this->assertNotNull($transfer);
        $this->assertSame('posted', $transfer->status);
    }

    public function test_transfer_rejects_status_outside_allowed_set(): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransfer(['status' => 'cancelled']);
    }

    #[DataProvider('requiredTransferFieldProvider')]
    public function test_transfer_requires_mandatory_fields(string $field): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransfer([$field => null]);
    }

    /** @return array<string, array{string}> */
    public static function requiredTransferFieldProvider(): array
    {
        return [
            'number' => ['number'],
            'transfer date' => ['transfer_date'],
            'source warehouse' => ['from_warehouse_id'],
            'destination warehouse' => ['to_warehouse_id'],
        ];
    }

    #[DataProvider('warehouseForeignKeyProvider')]
    public function test_transfer_requires_existing_warehouses(string $foreignKey): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransfer([$foreignKey => 999999]);
    }

    /** @return array<string, array{string}> */
    public static function warehouseForeignKeyProvider(): array
    {
        return [
            'source warehouse' => ['from_warehouse_id'],
            'destination warehouse' => ['to_warehouse_id'],
        ];
    }

    public function test_source_and_destination_warehouses_must_be_different(): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->expectException(QueryException::class);

        $this->insertTransfer([
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $warehouse->id,
        ]);
    }

    public function test_transfer_number_must_be_unique(): void
    {
        $this->insertTransfer(['number' => 'TRF-00000001']);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertTransfer(['number' => 'TRF-00000001']);
    }

    #[DataProvider('requiredTransferItemFieldProvider')]
    public function test_transfer_item_requires_mandatory_fields(string $field): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransferItem([$field => null]);
    }

    /** @return array<string, array{string}> */
    public static function requiredTransferItemFieldProvider(): array
    {
        return [
            'transfer' => ['transfer_id'],
            'product' => ['product_id'],
            'quantity' => ['quantity'],
        ];
    }

    public function test_transfer_item_requires_existing_transfer(): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransferItem(['transfer_id' => 999999]);
    }

    public function test_transfer_item_requires_existing_product(): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransferItem(['product_id' => 999999]);
    }

    #[DataProvider('nonPositiveQuantityProvider')]
    public function test_transfer_item_quantity_must_be_greater_than_zero(float $quantity): void
    {
        $this->expectException(QueryException::class);

        $this->insertTransferItem(['quantity' => $quantity]);
    }

    /** @return array<string, array{float}> */
    public static function nonPositiveQuantityProvider(): array
    {
        return [
            'zero' => [0.000],
            'negative' => [-0.001],
        ];
    }

    public function test_product_may_occur_only_once_in_a_transfer(): void
    {
        $transferId = $this->insertTransfer();
        $product = Product::factory()->create();

        $this->insertTransferItem([
            'transfer_id' => $transferId,
            'product_id' => $product->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertTransferItem([
            'transfer_id' => $transferId,
            'product_id' => $product->id,
        ]);
    }

    #[DataProvider('warehouseReferenceProvider')]
    public function test_warehouse_deletion_is_restricted_when_referenced_by_transfer(string $foreignKey): void
    {
        $warehouse = Warehouse::factory()->create();

        $this->insertTransfer([$foreignKey => $warehouse->id]);

        $this->expectException(QueryException::class);

        $warehouse->delete();
    }

    /** @return array<string, array{string}> */
    public static function warehouseReferenceProvider(): array
    {
        return [
            'source warehouse' => ['from_warehouse_id'],
            'destination warehouse' => ['to_warehouse_id'],
        ];
    }

    public function test_transfer_deletion_is_restricted_when_it_has_items(): void
    {
        $transferId = $this->insertTransfer();
        $this->insertTransferItem(['transfer_id' => $transferId]);

        $this->expectException(QueryException::class);

        DB::table('transfers')->where('id', $transferId)->delete();
    }

    public function test_product_deletion_is_restricted_when_referenced_by_transfer_item(): void
    {
        $product = Product::factory()->create();
        $this->insertTransferItem(['product_id' => $product->id]);

        $this->expectException(QueryException::class);

        $product->delete();
    }

    /** @param array<string, mixed> $attributes */
    private function insertTransfer(array $attributes = []): int
    {
        $this->assertTrue(
            Schema::hasTable('transfers'),
            'The transfers table has not been created yet.',
        );

        $sourceWarehouse = Warehouse::factory()->create();
        $destinationWarehouse = Warehouse::factory()->create();

        return DB::table('transfers')->insertGetId(array_merge([
            'number' => fake()->unique()->bothify('TRF-########'),
            'transfer_date' => '2026-08-06',
            'from_warehouse_id' => $sourceWarehouse->id,
            'to_warehouse_id' => $destinationWarehouse->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    /** @param array<string, mixed> $attributes */
    private function insertTransferItem(array $attributes = []): int
    {
        $this->assertTrue(
            Schema::hasTable('transfer_items'),
            'The transfer_items table has not been created yet.',
        );

        return DB::table('transfer_items')->insertGetId(array_merge([
            'transfer_id' => $this->insertTransfer(),
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1.000,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }
}
