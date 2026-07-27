<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;

class StockService
{
    public function receive(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'reserved' => 0]);
        $stock->quantity += $quantity;
        $stock->save();

        return $stock;
    }

    public function issue(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);

        if ($stock->available() < $quantity) {
            throw new \DomainException('Insufficient stock quantity');
        }

        $stock->quantity -= $quantity;
        $stock->save();

        return $stock;
    }

    public function reserve(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);


        if ($stock->available() < $quantity) {
            throw new \DomainException('Insufficient available stock for reservation');
        }

        $stock->reserved += $quantity;
        $stock->save();

        return $stock;
    }

    public function release(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);

        if ($stock->reserved < $quantity) {
            throw new \DomainException('Insufficient reserved quantity to release');
        }

        $stock->reserved -= $quantity;
        $stock->save();

        return $stock;
    }

    private function findStock(Warehouse $warehouse, Product $product): Stock
    {
        $stock = Stock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        if ($stock === null) {
            throw new \DomainException('Stock not found for warehouse and product');
        }

        return $stock;
    }
}
