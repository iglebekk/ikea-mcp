<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['item_no', 'product_type', 'series', 'first_observed_at', 'last_observed_at'];

    protected function casts(): array
    {
        return [
            'first_observed_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }

    public function translations(): HasMany
    {
        return $this->hasMany(ProductTranslation::class);
    }

    public function translation(): HasOne
    {
        return $this->hasOne(ProductTranslation::class);
    }

    public function marketProducts(): HasMany
    {
        return $this->hasMany(MarketProduct::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(ProductAsset::class)->orderBy('sort');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function stockStatuses(): HasMany
    {
        return $this->hasMany(StockStatus::class);
    }

    /** Scope to products present in a market with a translation for a language, both eager loaded. */
    public function scopeForMarket(Builder $query, string $market, string $language): Builder
    {
        return $query
            ->whereHas('marketProducts', fn (Builder $q) => $q->where('market', $market))
            ->whereHas('translations', fn (Builder $q) => $q->where('language', $language))
            ->with([
                'translation' => fn ($q) => $q->where('language', $language),
                'marketProducts' => fn ($q) => $q->where('market', $market),
            ]);
    }
}
