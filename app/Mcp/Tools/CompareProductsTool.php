<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Product;
use App\Services\IkeaApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Compare 2-5 IKEA products side by side with a structured diff of price, measurements, materials, ratings, variants and market availability. Products must be in the local catalog (use get_product first).')]
class CompareProductsTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'compare_products';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate([
                'item_numbers' => ['required', 'array', 'min:2', 'max:5'],
                'item_numbers.*' => ['string', 'max:255'],
            ]);

            $itemNos = array_map(IkeaApi::normalizeItemNo(...), $validated['item_numbers']);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        return $this->cached('compare_products', $market, $language, ['item_nos' => $itemNos], 'compare', function () use ($itemNos, $market, $language): array {
            $products = Product::query()
                ->whereIn('item_no', $itemNos)
                ->forMarket($market, $language)
                ->with(['assets', 'variants'])
                ->get()
                ->keyBy('item_no');

            $missing = array_values(array_diff($itemNos, $products->keys()->all()));

            if ($products->count() < 2) {
                return [
                    'data' => null,
                    'warnings' => ['Not enough products available in the local catalog to compare. Missing: '.implode(', ', $missing).'. Call get_product for each first.'],
                ];
            }

            return [
                'data' => [
                    'products' => $products->map(fn (Product $p): array => [
                        'item_no' => $p->item_no,
                        'name' => $p->translation?->name,
                        'type_name' => $p->translation?->type_name,
                        'url' => $p->marketProducts->first()?->url,
                    ])->values()->all(),
                    'comparison' => $this->diff($products),
                ],
                'warnings' => $missing === [] ? [] : ['Not in local catalog and excluded from the comparison: '.implode(', ', $missing).'.'],
            ];
        });
    }

    /**
     * Build the per-attribute diff, flagging rows where products differ.
     *
     * @param  Collection<string, Product>  $products
     * @return array<string, array{differs: bool, values: array<string, mixed>}>
     */
    private function diff(Collection $products): array
    {
        $attributes = [
            'price' => fn (Product $p) => $p->marketProducts->first()?->price,
            'regular_price' => fn (Product $p) => $p->marketProducts->first()?->regular_price,
            'campaign_price' => fn (Product $p) => $p->marketProducts->first()?->campaign_price,
            'currency' => fn (Product $p) => $p->marketProducts->first()?->currency,
            'status' => fn (Product $p) => $p->marketProducts->first()?->status,
            'online_sellable' => fn (Product $p) => $p->marketProducts->first()?->online_sellable,
            'rating' => fn (Product $p) => $p->marketProducts->first()?->rating_value,
            'rating_count' => fn (Product $p) => $p->marketProducts->first()?->rating_count,
            'measurements' => fn (Product $p) => $p->translation?->measurements,
            'materials' => fn (Product $p) => $p->translation?->materials,
            'care_instructions' => fn (Product $p) => $p->translation?->care_instructions,
            'packages' => fn (Product $p) => $p->translation?->packages,
            'variant_count' => fn (Product $p) => $p->variants->count(),
            'series' => fn (Product $p) => $p->series,
        ];

        $result = [];

        foreach ($attributes as $attribute => $resolver) {
            $values = $products->mapWithKeys(fn (Product $p) => [$p->item_no => $resolver($p)]);

            $result[$attribute] = [
                'differs' => $values->map(fn ($v) => json_encode($v))->unique()->count() > 1,
                'values' => $values->all(),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_numbers' => $schema->array()
                ->items($schema->string())
                ->min(2)
                ->max(5)
                ->description('2-5 IKEA item numbers to compare, any format.')
                ->required(),
            'market' => $schema->string()->description('ISO country code. Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code. Defaults to the configured language.'),
        ];
    }
}
