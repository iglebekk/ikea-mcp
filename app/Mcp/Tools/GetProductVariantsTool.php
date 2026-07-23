<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Product;
use App\Services\IkeaApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get the known variants (colors, sizes, related item numbers) of an IKEA product from the local catalog.')]
class GetProductVariantsTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'get_product_variants';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate(['item_number' => ['required', 'string', 'max:255']]);
            $itemNo = IkeaApi::normalizeItemNo($validated['item_number']);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        return $this->cached('get_product_variants', $market, $language, ['item_no' => $itemNo], 'variants', function () use ($itemNo, $market, $language): array {
            $product = Product::query()->where('item_no', $itemNo)->with('variants')->first();

            if ($product === null) {
                return [
                    'data' => [],
                    'warnings' => ["Product {$itemNo} is not in the local catalog. Call get_product first to import it."],
                ];
            }

            $variantItemNos = $product->variants->pluck('related_item_no');
            $knownVariants = Product::query()
                ->whereIn('item_no', $variantItemNos)
                ->forMarket($market, $language)
                ->get()
                ->keyBy('item_no');

            return [
                'data' => $product->variants->map(fn ($variant): array => [
                    'item_no' => $variant->related_item_no,
                    'variant_group' => $variant->variant_group,
                    'attributes' => $variant->variant_attributes,
                    'name' => $knownVariants->get($variant->related_item_no)?->translation?->name,
                    'price' => $knownVariants->get($variant->related_item_no)?->marketProducts->first()?->price,
                    'in_local_catalog' => $knownVariants->has($variant->related_item_no),
                ])->all(),
            ];
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_number' => $schema->string()->description('IKEA item number in any format.')->required(),
            'market' => $schema->string()->description('ISO country code. Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code. Defaults to the configured language.'),
        ];
    }
}
