<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

/**
 * Persists normalized IKEA data (from IkeaApi) into the local catalog.
 * Used by both the sync command and the read-through path in get_product.
 * Never overwrites existing values with empty ones.
 */
class IkeaImporter
{
    /** @var array{new: int, changed: int, unchanged: int} */
    public array $stats = ['new' => 0, 'changed' => 0, 'unchanged' => 0];

    /**
     * Import one search-result product card.
     *
     * @param  array<string, mixed>  $card
     */
    public function importCard(array $card, string $market, string $language, ?Category $category = null): Product
    {
        $product = $this->product($card['item_no'], [
            'product_type' => data_get($card, 'type_name'),
        ]);

        $this->translation($product, $language, [
            'name' => data_get($card, 'name'),
            'type_name' => data_get($card, 'type_name'),
            'description' => data_get($card, 'description'),
        ]);

        $this->marketProduct($product, $market, [
            'currency' => data_get($card, 'currency'),
            'price' => data_get($card, 'price'),
            'regular_price' => data_get($card, 'regular_price'),
            'url' => data_get($card, 'url'),
            'online_sellable' => data_get($card, 'online_sellable'),
            'rating_value' => data_get($card, 'rating_value'),
            'rating_count' => data_get($card, 'rating_count'),
            'status' => 'active',
        ]);

        if (filled(data_get($card, 'image_url'))) {
            $product->assets()->updateOrCreate(
                ['type' => 'image', 'url' => $card['image_url']],
                ['language' => $language, 'sort' => 0],
            );
        }

        $this->variants($product, data_get($card, 'variants', []));

        foreach ($this->categoriesFromPath(data_get($card, 'category_path', []), $market, $language) as $pathCategory) {
            $pathCategory->products()->syncWithoutDetaching($product->id);
        }

        if ($category !== null) {
            $category->products()->syncWithoutDetaching($product->id);
        }

        $this->bumpCatalogVersion($market);

        return $product;
    }

    /**
     * Create/refresh the category chain from a search card's category path
     * (ordered root to leaf) and return the created categories.
     *
     * @param  array<int, array{id: mixed, name: mixed}>  $path
     * @return array<int, Category>
     */
    private function categoriesFromPath(array $path, string $market, string $language): array
    {
        $categories = [];
        $parent = null;

        foreach ($path as $level) {
            if (blank(data_get($level, 'id')) || blank(data_get($level, 'name'))) {
                continue;
            }

            $parent = Category::query()->updateOrCreate(
                ['market' => $market, 'language' => $language, 'ikea_id' => (string) $level['id']],
                ['name' => (string) $level['name'], 'parent_id' => $parent?->id, 'is_active' => true],
            );

            $categories[] = $parent;
        }

        return $categories;
    }

    /**
     * Import full PIP details for a product.
     *
     * @param  array<string, mixed>  $details
     */
    public function importDetails(array $details, string $market, string $language): Product
    {
        $product = $this->product($details['item_no'], [
            'product_type' => data_get($details, 'type_name'),
        ]);

        $this->translation($product, $language, [
            'name' => data_get($details, 'name'),
            'type_name' => data_get($details, 'type_name'),
            'description' => data_get($details, 'description'),
            'benefits' => data_get($details, 'benefits'),
            'materials' => data_get($details, 'materials'),
            'care_instructions' => data_get($details, 'care_instructions'),
            'safety_information' => data_get($details, 'safety_information'),
            'technical_details' => data_get($details, 'technical_details'),
            'measurements' => data_get($details, 'measurements'),
            'packages' => data_get($details, 'packages'),
        ]);

        $this->marketProduct($product, $market, [
            'currency' => data_get($details, 'currency'),
            'price' => data_get($details, 'price'),
            'url' => data_get($details, 'url'),
            'status' => 'active',
        ]);

        foreach (array_values(data_get($details, 'images', [])) as $sort => $url) {
            $product->assets()->updateOrCreate(
                ['type' => 'image', 'url' => $url],
                ['language' => $language, 'sort' => $sort],
            );
        }

        foreach (data_get($details, 'documents', []) as $document) {
            $product->assets()->updateOrCreate(
                ['type' => data_get($document, 'type', 'document'), 'url' => $document['url']],
                ['language' => $language, 'title' => data_get($document, 'title')],
            );
        }

        $this->variants($product, data_get($details, 'variants', []));

        $this->bumpCatalogVersion($market);

        return $product->refresh();
    }

    /**
     * Mark market products as discontinued when a full market sync no longer observes them.
     *
     * @param  array<int, string>  $observedItemNos
     */
    public function markUnobservedAsDiscontinued(string $market, array $observedItemNos): int
    {
        $count = 0;

        Product::query()
            ->whereNotIn('item_no', $observedItemNos)
            ->whereHas('marketProducts', fn ($q) => $q->where('market', $market)->where('status', 'active'))
            ->each(function (Product $product) use ($market, &$count): void {
                $product->marketProducts()
                    ->where('market', $market)
                    ->update(['status' => 'discontinued', 'last_checked_at' => now(), 'last_changed_at' => now()]);
                $count++;
            });

        if ($count > 0) {
            $this->bumpCatalogVersion($market);
        }

        return $count;
    }

    /**
     * Cache version for a market; part of every response-cache key so that
     * imports invalidate cached MCP responses without needing cache tags.
     */
    public static function catalogVersion(string $market): int
    {
        return (int) Cache::get("ikea:catalog-version:{$market}", 1);
    }

    public function bumpCatalogVersion(string $market): void
    {
        Cache::add("ikea:catalog-version:{$market}", 1);
        Cache::increment("ikea:catalog-version:{$market}");
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(string $itemNo, array $attributes): Product
    {
        $product = Product::query()->firstOrCreate(
            ['item_no' => $itemNo],
            ['first_observed_at' => now()],
        );

        if ($product->wasRecentlyCreated) {
            $this->stats['new']++;
        }

        $product->fill($this->filled($attributes));
        $product->last_observed_at = now();
        $product->save();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function translation(Product $product, string $language, array $attributes): void
    {
        if (blank(data_get($attributes, 'name'))) {
            return;
        }

        $translation = $product->translations()->firstOrNew(['language' => $language]);
        $translation->fill($this->filled($attributes));
        $translation->save();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function marketProduct(Product $product, string $market, array $attributes): void
    {
        $record = $product->marketProducts()->firstOrNew(['market' => $market]);
        $record->fill($this->filled($attributes));

        $changed = $record->isDirty();
        $record->last_checked_at = now();

        if ($changed && $record->exists) {
            $this->stats['changed']++;
        } elseif ($record->exists) {
            $this->stats['unchanged']++;
        }

        if ($changed) {
            $record->last_changed_at = now();
        }

        $record->save();
    }

    /**
     * @param  array<int, string>  $relatedItemNos
     */
    private function variants(Product $product, array $relatedItemNos): void
    {
        foreach ($relatedItemNos as $relatedItemNo) {
            if ($relatedItemNo !== $product->item_no) {
                $product->variants()->firstOrCreate(['related_item_no' => $relatedItemNo]);
            }
        }
    }

    /**
     * Drop null/empty values so stale-but-good data is never overwritten by
     * an empty upstream response.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function filled(array $attributes): array
    {
        return array_filter($attributes, fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '');
    }
}
