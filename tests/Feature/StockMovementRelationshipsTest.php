<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_movement_belongs_to_warehouse_and_product(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $movement = StockMovement::factory()->for($warehouse)->for($product)->create();

        $this->assertInstanceOf(BelongsTo::class, $movement->warehouse());
        $this->assertTrue($movement->warehouse->is($warehouse));
        $this->assertInstanceOf(BelongsTo::class, $movement->product());
        $this->assertTrue($movement->product->is($product));
    }

    public function test_stock_movement_morphs_to_receipt_and_receipt_has_many_movements(): void
    {
        $receipt = Receipt::factory()->create();
        $movement = StockMovement::factory()->forReceipt($receipt)->create();

        $this->assertInstanceOf(MorphTo::class, $movement->source());
        $this->assertTrue($movement->source->is($receipt));
        $this->assertInstanceOf(MorphMany::class, $receipt->stockMovements());
        $this->assertTrue($receipt->stockMovements->contains($movement));
    }

    public function test_stock_movement_morphs_to_issue_and_issue_has_many_movements(): void
    {
        $issue = Issue::factory()->create();
        $movement = StockMovement::factory()->forIssue($issue)->create();

        $this->assertInstanceOf(MorphTo::class, $movement->source());
        $this->assertTrue($movement->source->is($issue));
        $this->assertInstanceOf(MorphMany::class, $issue->stockMovements());
        $this->assertTrue($issue->stockMovements->contains($movement));
    }

    public function test_warehouse_and_product_have_many_stock_movements(): void
    {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();
        $firstMovement = StockMovement::factory()->for($warehouse)->for($product)->create();
        $secondMovement = StockMovement::factory()->for($warehouse)->for($product)->create();

        $this->assertInstanceOf(HasMany::class, $warehouse->stockMovements());
        $this->assertCount(2, $warehouse->stockMovements);
        $this->assertTrue($warehouse->stockMovements->contains($firstMovement));
        $this->assertTrue($warehouse->stockMovements->contains($secondMovement));
        $this->assertInstanceOf(HasMany::class, $product->stockMovements());
        $this->assertCount(2, $product->stockMovements);
    }

    public function test_stock_movement_belongs_to_its_creator(): void
    {
        $user = User::factory()->create();
        $movement = StockMovement::factory()->create(['created_by' => $user->id]);

        $this->assertInstanceOf(BelongsTo::class, $movement->createdBy());
        $this->assertTrue($movement->createdBy->is($user));
    }
}
