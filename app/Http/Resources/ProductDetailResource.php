<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Complete product representation for get_product.
 * Expects translation, marketProducts, assets, variants and categories loaded.
 *
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
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
            'product_type' => $this->product_type,
            'series' => $this->series,
            'description' => $this->translation?->description,
            'benefits' => $this->translation?->benefits,
            'materials' => $this->translation?->materials,
            'care_instructions' => $this->translation?->care_instructions,
            'safety_information' => $this->translation?->safety_information,
            'technical_details' => $this->translation?->technical_details,
            'measurements' => $this->translation?->measurements,
            'packages' => $this->translation?->packages,
            'price' => $marketProduct?->price,
            'regular_price' => $marketProduct?->regular_price,
            'campaign_price' => $marketProduct?->campaign_price,
            'campaign_period' => $marketProduct?->campaign_starts_at !== null || $marketProduct?->campaign_ends_at !== null ? [
                'starts_at' => $marketProduct?->campaign_starts_at?->toIso8601String(),
                'ends_at' => $marketProduct?->campaign_ends_at?->toIso8601String(),
            ] : null,
            'currency' => $marketProduct?->currency,
            'status' => $marketProduct?->status,
            'online_sellable' => $marketProduct?->online_sellable,
            'rating_value' => $marketProduct?->rating_value,
            'rating_count' => $marketProduct?->rating_count,
            'url' => $marketProduct?->url,
            'images' => $this->assets->where('type', 'image')->pluck('url')->values(),
            'documents' => $this->assets->where('type', '!=', 'image')->map(fn ($asset) => [
                'type' => $asset->type,
                'title' => $asset->title,
                'url' => $asset->url,
            ])->values(),
            'variants' => $this->variants->map(fn ($variant) => [
                'item_no' => $variant->related_item_no,
                'variant_group' => $variant->variant_group,
                'attributes' => $variant->variant_attributes,
            ]),
            'categories' => $this->categories->map(fn ($category) => [
                'id' => $category->ikea_id,
                'name' => $category->name,
            ]),
            'first_observed_at' => $this->first_observed_at?->toIso8601String(),
            'last_observed_at' => $this->last_observed_at?->toIso8601String(),
            'last_checked_at' => $marketProduct?->last_checked_at?->toIso8601String(),
            'last_changed_at' => $marketProduct?->last_changed_at?->toIso8601String(),
        ];
    }
}
