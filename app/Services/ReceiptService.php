<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use BadMethodCallException;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReceiptService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Create a receipt.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Receipt
    {
        $data = array_merge(
            $data,
            [
                'status' => 'draft',
            ],
        );

        return Receipt::create($data);
    }

    /**
     * Add an item to a receipt.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(Receipt $receipt, array $data): ReceiptItem
    {
        $this->ensureDraft($receipt);

        return $receipt->items()->create($data);
    }

    /**
     * Update a receipt item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(ReceiptItem $item, array $data): ReceiptItem
    {
        $this->ensureDraft($item->receipt);

        $item->fill($data);
        $item->save();

        return $item;
    }

    /**
     * Remove a receipt item.
     */
    public function removeItem(ReceiptItem $item): void
    {
        $this->ensureDraft($item->receipt);

        $item->delete();
    }

    /**
     * Post a receipt.
     */
    public function post(Receipt $receipt): void
    {
        if ($receipt->items()->count() === 0) {
            throw new DomainException('Cannot post a receipt with no items');
        }

        $this->ensureDraft($receipt);

        DB::transaction(function () use ($receipt) {
            foreach ($receipt->items as $item) {
                $this->stockService->increase(
                    $receipt->warehouse,
                    $item->product,
                    (float) $item->quantity,
                );
            }

            $receipt->update(['status' => 'posted']);
        });
    }

    /**
     * Cancel a receipt.
     */
    public function cancel(Receipt $receipt): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    private function ensureDraft(Receipt $receipt): void
    {
        if ($receipt->status !== 'draft') {
            throw new DomainException('Cannot post a receipt that is not in draft status');
        }
    }
}
