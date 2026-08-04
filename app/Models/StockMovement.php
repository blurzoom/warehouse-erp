<?php

namespace App\Models;

use App\Enums\StockMovementType;
use Database\Factories\StockMovementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['warehouse_id', 'product_id', 'type', 'quantity', 'balance_after', 'source_type', 'source_id', 'created_by'])]
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => StockMovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new DomainException('Stock movements are immutable.');
        });

        static::deleting(function (): void {
            throw new DomainException('Stock movements are immutable.');
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
