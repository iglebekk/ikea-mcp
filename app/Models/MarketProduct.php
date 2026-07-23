<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'market', 'currency', 'price', 'regular_price', 'campaign_price',
        'campaign_starts_at', 'campaign_ends_at', 'url', 'status', 'online_sellable',
        'rating_value', 'rating_count', 'last_checked_at', 'last_changed_at',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'regular_price' => 'decimal:2',
            'campaign_price' => 'decimal:2',
            'campaign_starts_at' => 'datetime',
            'campaign_ends_at' => 'datetime',
            'online_sellable' => 'boolean',
            'rating_value' => 'decimal:2',
            'last_checked_at' => 'datetime',
            'last_changed_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
