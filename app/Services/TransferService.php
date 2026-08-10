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

            foreach ($transfer->items as $item) {
                $sourceStock = $this->stockService->decrease(
                    $transfer->fromWarehouse,
                    $item->product,
                    (float) $item->quantity,
                );

                StockMovement::query()->create([
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => -(float) $item->quantity,
                    'balance_after' => $sourceStock->quantity,
                    'source_type' => $transfer->getMorphClass(),
                    'source_id' => $transfer->getKey(),
                    'created_by' => null,
                ]);

                $destinationStock = $this->stockService->increase(
                    $transfer->toWarehouse,
                    $item->product,
                    (float) $item->quantity,
                );

                StockMovement::query()->create([
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'product_id' => $item->product_id,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => (float) $item->quantity,
                    'balance_after' => $destinationStock->quantity,
                    'source_type' => $transfer->getMorphClass(),
                    'source_id' => $transfer->getKey(),
                    'created_by' => null,
                ]);
            }

            $transfer->update(['status' => TransferStatus::Posted]);
        });
    }
}
