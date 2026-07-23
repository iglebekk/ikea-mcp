<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Http\Resources\ProductDetailResource;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Product;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get complete information about one IKEA product: description, price, measurements, materials, care, packages, images, documents, variants and categories. Looks up the local catalog first and fetches the product from IKEA on demand if it is missing.')]
class GetProductTool extends Tool
{
    use InteractsWithCatalog;

    public function __construct(public IkeaApi $api, public IkeaImporter $importer) {}

    protected string $name = 'get_product';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate([
                'item_number' => ['required', 'string', 'max:255'],
            ]);

            $itemNo = IkeaApi::normalizeItemNo($validated['item_number']);

            return $this->cached('get_product', $market, $language, ['item_no' => $itemNo], 'product', function () use ($itemNo, $market, $language): array {
                $product = $this->find($itemNo, $market, $language);
                $source = 'local_catalog';
                $warnings = [];

                if ($product === null) {
                    try {
                        $details = $this->api->productDetails($market, $language, $itemNo);
                        $this->importer->importDetails($details, $market, $language);
                        $product = $this->find($itemNo, $market, $language);
                        $source = 'ikea_live';
                    } catch (IkeaException $e) {
                        if ($e->reason !== IkeaException::BLOCKED) {
                            throw $e;
                        }

                        // The PIP detail endpoint (www.ikea.com) is blocked, but the
                        // search CDN is not — fall back to the search card for
                        // partial data rather than failing the whole request.
                        $product = $this->fallbackFromSearch($itemNo, $market, $language);

                        if ($product === null) {
                            throw $e;
                        }

                        $source = 'ikea_search_fallback';
                        $warnings[] = 'Full product details are currently blocked by IKEA (HTTP 403); returning partial data from search results. Fields such as materials, measurements, care and documents may be missing. Use refresh_product to retry the full detail fetch later.';
                    }
                }

                if ($product === null) {
                    throw new IkeaException(
                        IkeaException::NOT_IN_MARKET,
                        "Product {$itemNo} could not be loaded for market {$market}/{$language}.",
                    );
                }

                $lastChecked = $product->marketProducts->first()?->last_checked_at;
                $staleAfter = now()->subDays(config('ikea.product_stale_after_days'));

                if ($lastChecked !== null && $lastChecked->lt($staleAfter)) {
                    $warnings[] = 'Product data has not been checked against IKEA recently. Use refresh_product for a forced re-check.';
                }

                return [
                    'data' => (new ProductDetailResource($product))->resolve(),
                    'source' => $source,
                    'last_checked_at' => $lastChecked?->toIso8601String(),
                    'possibly_stale' => ($lastChecked !== null && $lastChecked->lt($staleAfter)) || $source === 'ikea_search_fallback',
                    'warnings' => $warnings,
                ];
            });
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }
    }

    /**
     * Look the item up through search_products (a different, unblocked host) and
     * import the resulting card, giving partial product data when the PIP detail
     * endpoint is blocked. Returns null when search does not surface the item.
     */
    private function fallbackFromSearch(string $itemNo, string $market, string $language): ?Product
    {
        $result = $this->api->searchProducts($market, $language, $itemNo, 10);

        $card = collect($result['products'])->firstWhere('item_no', $itemNo);

        if ($card === null) {
            return null;
        }

        $this->importer->importCard($card, $market, $language);

        return $this->find($itemNo, $market, $language);
    }

    private function find(string $itemNo, string $market, string $language): ?Product
    {
        return Product::query()
            ->where('item_no', $itemNo)
            ->forMarket($market, $language)
            ->with(['assets', 'variants', 'categories' => fn ($q) => $q->where('market', $market)])
            ->first();
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'item_number' => $schema->string()
                ->description('IKEA item number in any format: "00263850", "002.638.50", "s49903093", or a product URL containing the item number.')
                ->required(),
            'market' => $schema->string()->description('ISO country code. Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code. Defaults to the configured language.'),
        ];
    }
}
