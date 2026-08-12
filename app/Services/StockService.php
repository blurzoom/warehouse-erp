<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Stock;
use App\Models\Warehouse;
use DomainException;
use LogicException;

class StockService
{
    /**
     * @param  array<int, int|float>  $quantitiesByProductId
     * @return array<int, array{
     *     source_balance: float,
     *     destination_balance: float
     * }>
     */
    public function transfer(
        Warehouse $sourceWarehouse,
        Warehouse $destinationWarehouse,
        array $quantitiesByProductId,
    ): array {
        if ((new Stock)->getConnection()->transactionLevel() === 0) {
            throw new LogicException('Stock transfer requires an active database transaction.');
        }

        if ($sourceWarehouse->is($destinationWarehouse)) {
            throw new DomainException('Source and destination warehouses must be different');
        }

        if ($quantitiesByProductId === []) {
            throw new DomainException('Transfer must contain at least one item');
        }

        ksort($quantitiesByProductId, SORT_NUMERIC);

        $productIds = array_keys($quantitiesByProductId);
        $existingDestinationProductIds = Stock::query()
            ->where('warehouse_id', $destinationWarehouse->getKey())
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->all();
        $missingDestinationProductIds = array_values(array_diff(
            $productIds,
            $existingDestinationProductIds,
        ));
        sort($missingDestinationProductIds, SORT_NUMERIC);

        $timestamp = now();

        foreach ($missingDestinationProductIds as $productId) {
            Stock::query()->insertOrIgnore([
                'warehouse_id' => $destinationWarehouse->getKey(),
                'product_id' => $productId,
                'quantity' => 0,
                'reserved' => 0,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        }

        $stockPairs = [];

        foreach (array_keys($quantitiesByProductId) as $productId) {
            foreach ([$sourceWarehouse->getKey(), $destinationWarehouse->getKey()] as $warehouseId) {
                $stockPairs[$warehouseId.':'.$productId] = [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                ];
            }
        }

        $stockPairs = array_values($stockPairs);
        usort($stockPairs, fn (array $left, array $right): int => [
            $left['warehouse_id'],
            $left['product_id'],
        ] <=> [
            $right['warehouse_id'],
            $right['product_id'],
        ]);

        $lockedStocks = [];

        foreach ($stockPairs as $stockPair) {
            $stock = Stock::query()
                ->where('warehouse_id', $stockPair['warehouse_id'])
                ->where('product_id', $stockPair['product_id'])
                ->lockForUpdate()
                ->first();

            if ($stock === null) {
                throw new DomainException('Insufficient stock quantity');
            }

            $lockedStocks[$stockPair['warehouse_id']][$stockPair['product_id']] = $stock;
        }

        foreach ($quantitiesByProductId as $productId => $quantity) {
            $sourceStock = $lockedStocks[$sourceWarehouse->getKey()][$productId];

            if ($sourceStock->available() < $quantity) {
                throw new DomainException('Insufficient stock quantity');
            }
        }

        $balances = [];

        foreach ($quantitiesByProductId as $productId => $quantity) {
            $sourceStock = $lockedStocks[$sourceWarehouse->getKey()][$productId];
            $destinationStock = $lockedStocks[$destinationWarehouse->getKey()][$productId];

            $sourceStock->quantity -= $quantity;
            $sourceStock->save();

            $destinationStock->quantity += $quantity;
            $destinationStock->save();

            $balances[$productId] = [
                'source_balance' => (float) $sourceStock->quantity,
                'destination_balance' => (float) $destinationStock->quantity,
            ];
        }

        return $balances;
    }

    public function increase(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = Stock::firstOrCreate(
            ['product_id' => $product->id, 'warehouse_id' => $warehouse->id],
            ['quantity' => 0, 'reserved' => 0]);
        $stock->quantity += $quantity;
        $stock->save();

        return $stock;
    }

    public function decrease(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);

        if ($stock->available() < $quantity) {
            throw new DomainException('Insufficient stock quantity');
        }

        $stock->quantity -= $quantity;
        $stock->save();

        return $stock;
    }

    public function reserve(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);

        if ($stock->available() < $quantity) {
            throw new DomainException('Insufficient available stock for reservation');
        }

        $stock->reserved += $quantity;
        $stock->save();

        return $stock;
    }

    public function release(Warehouse $warehouse, Product $product, float $quantity): Stock
    {
        $stock = $this->findStock($warehouse, $product);

        if ($stock->reserved < $quantity) {
            throw new DomainException('Insufficient reserved quantity to release');
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
            throw new DomainException('Stock not found for warehouse and product');
        }

        return $stock;
    }
}
