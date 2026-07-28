<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_belongs_to_category(): void
    {
        ['category' => $category, 'unit' => $unit] = $this->createRelations();
        $product = Product::factory()
            ->for($category)
            ->for($unit)
            ->create();

        $this->assertInstanceOf(BelongsTo::class, $product->category());
        $this->assertTrue($product->category->is($category));
    }

    private function createRelations(): array
    {
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();

        return compact('category', 'unit');
    }

    public function test_product_belongs_to_unit(): void
    {
        ['category' => $category, 'unit' => $unit] = $this->createRelations();
        $product = Product::factory()
            ->for($category)
            ->for($unit)
            ->create();

        $this->assertInstanceOf(BelongsTo::class, $product->unit());
        $this->assertTrue($product->unit->is($unit));
    }

    public function test_category_has_many_products(): void
    {
        ['category' => $category, 'unit' => $unit] = $this->createRelations();
        $product = Product::factory()
            ->for($category)
            ->for($unit)
            ->create([
                'sku' => 'category-product-001',
            ]);
        $this->assertInstanceOf(HasMany::class, $category->products());
        $this->assertTrue($category->products->contains($product));

    }

    public function test_unit_has_many_products(): void
    {
        ['category' => $category, 'unit' => $unit] = $this->createRelations();
        $product = Product::factory()
            ->for($category)
            ->for($unit)
            ->create([
                'sku' => 'unit-product-001',
            ]);
        $this->assertInstanceOf(HasMany::class, $unit->products());
        $this->assertTrue($unit->products->contains($product));
    }
}
