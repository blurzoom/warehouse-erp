<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\TransferStatus;
use App\Models\StockMovement;
use App\Models\Transfer;
use DomainException;
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Post a transfer.
     */
    public function post(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $transfer = Transfer::query()
                ->with(['fromWarehouse', 'toWarehouse', 'items.product'])
                ->lockForUpdate()
                ->findOrFail($transfer->getKey());

            if ($transfer->status !== TransferStatus::Draft) {
                throw new DomainException('Cannot post a transfer that is not in draft status');
            }

            if ($transfer->items->isEmpty()) {
                throw new DomainException('Cannot post a transfer with no items');
            }

            $quantitiesByProductId = [];

            foreach ($transfer->items as $item) {
                $quantitiesByProductId[(int) $item->product_id] = (float) $item->quantity;
            }

            $balancesByProductId = $this->stockService->transfer(
                $transfer->fromWarehouse,
                $transfer->toWarehouse,
                $quantitiesByProductId,
            );

            foreach ($transfer->items as $item) {
                $productId = (int) $item->product_id;
                $quantity = $quantitiesByProductId[$productId];
                $balances = $balancesByProductId[$productId];

                StockMovement::query()->create([
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'product_id' => $productId,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => -$quantity,
                    'balance_after' => $balances['source_balance'],
                    'source_type' => $transfer->getMorphClass(),
                    'source_id' => $transfer->getKey(),
                    'created_by' => null,
                ]);

                StockMovement::query()->create([
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'product_id' => $productId,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => $quantity,
                    'balance_after' => $balances['destination_balance'],
                    'source_type' => $transfer->getMorphClass(),
                    'source_id' => $transfer->getKey(),
                    'created_by' => null,
                ]);
            }

            $transfer->update(['status' => TransferStatus::Posted]);
        });
    }
}
