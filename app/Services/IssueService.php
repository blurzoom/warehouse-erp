<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Issue;
use App\Models\IssueItem;
use BadMethodCallException;
use DomainException;
use Illuminate\Support\Facades\DB;

class IssueService
{
    public function __construct(private readonly StockService $stockService) {}

    /**
     * Create an issue.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Issue
    {
        $data = array_merge(
            $data,
            [
                'status' => 'draft',
            ],
        );

        return Issue::create($data);
    }

    /**
     * Add an item to an issue.
     *
     * @param  array<string, mixed>  $data
     */
    public function addItem(Issue $issue, array $data): IssueItem
    {
        $this->ensureDraft($issue);

        return $issue->items()->create($data);
    }

    /**
     * Update an issue item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateItem(IssueItem $item, array $data): IssueItem
    {
        $this->ensureDraft($item->issue);

        $item->fill($data);
        $item->save();

        return $item;
    }

    /**
     * Remove an issue item.
     */
    public function removeItem(IssueItem $item): void
    {
        $this->ensureDraft($item->issue);

        $item->delete();
    }

    /**
     * Post an issue.
     */
    public function post(Issue $issue): void
    {
        if ($issue->items()->count() === 0) {
            throw new DomainException('Cannot post an issue with no items');
        }

        $this->ensureDraft($issue);

        DB::transaction(function () use ($issue) {
            foreach ($issue->items as $item) {
                $this->stockService->decrease(
                    $issue->warehouse,
                    $item->product,
                    (float) $item->quantity,
                );
            }

            $issue->update(['status' => 'posted']);
        });
    }

    /**
     * Cancel an issue.
     */
    public function cancel(Issue $issue): void
    {
        throw new BadMethodCallException('Not implemented.');
    }

    private function ensureDraft(Issue $issue): void
    {
        if ($issue->status !== 'draft') {
            throw new DomainException('Cannot post an issue that is not in draft status');
        }
    }
}
