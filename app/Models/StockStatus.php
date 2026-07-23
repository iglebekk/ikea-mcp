<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'market', 'store_id', 'store_name', 'postal_code',
        'quantity', 'probability', 'restock_expected_at', 'checked_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'restock_expected_at' => 'date',
            'checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
