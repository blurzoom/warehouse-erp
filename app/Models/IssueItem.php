<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IssueItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'issue_id',
        'product_id',
        'quantity',
        'comment',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
