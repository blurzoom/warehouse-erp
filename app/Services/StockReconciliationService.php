<?php

namespace App\Services;

use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class StockReconciliationService
{
    public function reconcile(): Collection
    {
        $pairs = Stock::query()
            ->select(['warehouse_id', 'product_id'])
            ->get()
            ->concat(
                StockMovement::query()
                    ->select(['warehouse_id', 'product_id'])
                    ->distinct()
                    ->get()
            )
            ->unique(
                fn ($pair): string => $pair->warehouse_id.'-'.$pair->product_id
            );

        return $pairs
            ->map(function ($pair): array {
                $expectedQuantity = (float) StockMovement::query()
                    ->where('warehouse_id', $pair->warehouse_id)
                    ->where('product_id', $pair->product_id)
                    ->sum('quantity');

                $actualQuantity = (float) (
                    Stock::query()
                        ->where('warehouse_id', $pair->warehouse_id)
                        ->where('product_id', $pair->product_id)
                        ->value('quantity') ?? 0
                );

                return [
                    'warehouse_id' => $pair->warehouse_id,
                    'product_id' => $pair->product_id,
                    'expected_quantity' => $expectedQuantity,
                    'actual_quantity' => $actualQuantity,
                    'difference' => round(
                        $actualQuantity - $expectedQuantity,
                        3
                    ),
                ];
            })
            ->filter(
                fn (array $discrepancy): bool => $discrepancy['difference'] !== 0.0
            )
            ->values();
    }
}
