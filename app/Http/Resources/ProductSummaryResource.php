<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Compact product card used by search, category listings and comparisons.
 * Expects the Product::forMarket scope's eager loads (translation, marketProducts).
 *
 * @mixin Product
 */
class ProductSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $marketProduct = $this->marketProducts->first();

        return [
            'item_no' => $this->item_no,
            'name' => $this->translation?->name,
            'type_name' => $this->translation?->type_name,
            'description' => $this->translation?->description,
            'series' => $this->series,
            'price' => $marketProduct?->price,
            'regular_price' => $marketProduct?->regular_price,
            'campaign_price' => $marketProduct?->campaign_price,
            'currency' => $marketProduct?->currency,
            'status' => $marketProduct?->status,
            'online_sellable' => $marketProduct?->online_sellable,
            'rating_value' => $marketProduct?->rating_value,
            'rating_count' => $marketProduct?->rating_count,
            'url' => $marketProduct?->url,
            'image_url' => $this->whenLoaded('assets', fn () => $this->assets->firstWhere('type', 'image')?->url),
            'last_checked_at' => $marketProduct?->last_checked_at?->toIso8601String(),
        ];
    }
}
