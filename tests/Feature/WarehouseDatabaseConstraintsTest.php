<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_name_must_be_unique(): void
    {
        $category = $this->createCategory([
            'name' => 'Food',
        ]);

        $this->assertModelExists($category);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createCategory([
            'name' => 'Food',
        ]);
    }

    private function createCategory(array $attributes = []): Category
    {
        return Category::factory()->create($attributes);
    }

    public function test_unit_name_must_be_unique(): void
    {
        $unit = $this->createUnit([
            'name' => 'Kilogram',
            'code' => 'kg',
            'decimal_places' => 3,
        ]);

        $this->assertModelExists($unit);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createUnit([
            'name' => 'Kilogram',
            'code' => 'kilo',
            'decimal_places' => 3,
        ]);
    }

    private function createUnit(array $attributes = []): Unit
    {
        return Unit::factory()->create($attributes);
    }

    public function test_unit_code_must_be_unique(): void
    {
        $unit = $this->createUnit([
            'name' => 'Kilogram',
            'code' => 'kg',
            'decimal_places' => 3,
        ]);

        $this->assertModelExists($unit);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createUnit([
            'name' => 'Gram',
            'code' => 'kg',
            'decimal_places' => 3,
        ]);
    }

    public function test_unit_decimal_places_must_not_exceed_three(): void
    {
        $this->expectException(QueryException::class);

        $this->createUnit([
            'name' => 'Kilogram',
            'code' => 'kg',
            'decimal_places' => 4,
        ]);
    }

    public function test_product_sku_must_be_unique(): void
    {
        $product = $this->createProduct([
            'sku' => 'milk-001',
        ]);

        $this->assertModelExists($product);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createProduct([
            'sku' => 'milk-001',
        ]);
    }

    /**
     * Creates a new product instance with optional attributes.
     *
     * @param  array  $attributes  Optional attributes for the product. Default is an empty array.
     * @return Product A newly created product instance.
     */
    private function createProduct(array $attributes = []): Product
    {
        $categoryId = $attributes['category_id'] ?? $this->createCategory()->id;
        $unitId = $attributes['unit_id'] ?? $this->createUnit()->id;

        unset($attributes['category_id'], $attributes['unit_id']);

        return Product::factory()->create(array_merge([
            'category_id' => $categoryId,
            'unit_id' => $unitId,
        ], $attributes));
    }

    public function test_product_barcode_must_be_unique(): void
    {
        $product = $this->createProduct([
            'sku' => 'milk-001',
            'barcode' => '1234567890',
        ]);

        $this->assertModelExists($product);

        $this->expectException(UniqueConstraintViolationException::class);

        $this->createProduct([
            'sku' => 'milk-002',
            'barcode' => '1234567890',
        ]);
    }

    public function test_product_barcode_can_be_null_for_multiple_products(): void
    {
        $category = $this->createCategory();
        $unit = $this->createUnit();

        $firstProduct = $this->createProduct([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => 'water-001',
            'barcode' => null,
        ]);

        $secondProduct = $this->createProduct([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Water',
            'sku' => 'water-002',
            'barcode' => null,
        ]);

        $this->assertModelExists($firstProduct);
        $this->assertModelExists($secondProduct);
        $this->assertNull($firstProduct->barcode);
        $this->assertNull($secondProduct->barcode);
    }

    public function test_product_requires_existing_category(): void
    {
        $this->expectException(QueryException::class);

        $this->createProduct([
            'category_id' => 999,
        ]);
    }

    public function test_product_requires_existing_unit(): void
    {
        $this->expectException(QueryException::class);

        $this->createProduct([
            'unit_id' => 999,
        ]);
    }
}
