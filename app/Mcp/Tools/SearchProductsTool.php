<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Services\IkeaApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search IKEA products directly at IKEA by free text, with optional category, price and product-type filters. Search results are never stored locally; use get_product to cache the complete details of one product by item number.')]
class SearchProductsTool extends Tool
{
    use InteractsWithCatalog;

    public function __construct(public IkeaApi $api) {}

    protected string $name = 'search_products';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'category_id' => ['nullable', 'string', 'max:50'],
            'product_type' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0'],
            'include_discontinued' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('ikea.max_page_size')],
        ]);

        try {
            $perPage = (int) data_get($validated, 'per_page', config('ikea.max_page_size'));
            $result = $this->api->searchProducts(
                $market,
                $language,
                trim((string) data_get($validated, 'query', '')),
                $perPage,
                data_get($validated, 'category_id'),
            );
            $products = collect($result['products'])
                ->filter(fn (array $product): bool => $this->matchesFilters($product, $validated))
                ->map(fn (array $product): array => $this->summary($product))
                ->values();
            $filtersApplied = filled(data_get($validated, 'product_type'))
                || filled(data_get($validated, 'min_price'))
                || filled(data_get($validated, 'max_price'));
            $total = $filtersApplied ? $products->count() : $result['total'];

            return $this->envelope($market, $language, [
                'data' => $products->all(),
                'pagination' => [
                    'per_page' => $perPage,
                    'total' => $total,
                    'returned' => $products->count(),
                ],
                'source' => 'ikea_live',
                'warnings' => $total !== null && $total > $products->count()
                    ? ["IKEA returned {$products->count()} of {$total} matching products. Refine the search to narrow the result set."]
                    : [],
            ]);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }
    }

    /**
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $filters
     */
    private function matchesFilters(array $product, array $filters): bool
    {
        $type = data_get($filters, 'product_type');
        $price = data_get($product, 'price');

        return (! filled($type) || Str::contains(Str::lower((string) data_get($product, 'type_name')), Str::lower((string) $type)))
            && (! filled(data_get($filters, 'min_price')) || $price >= data_get($filters, 'min_price'))
            && (! filled(data_get($filters, 'max_price')) || $price <= data_get($filters, 'max_price'));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<string, mixed>
     */
    private function summary(array $product): array
    {
        return [
            'item_no' => $product['item_no'],
            'name' => data_get($product, 'name'),
            'type_name' => data_get($product, 'type_name'),
            'description' => data_get($product, 'description'),
            'series' => null,
            'price' => data_get($product, 'price'),
            'regular_price' => data_get($product, 'regular_price'),
            'campaign_price' => null,
            'currency' => data_get($product, 'currency'),
            'status' => 'active',
            'online_sellable' => data_get($product, 'online_sellable'),
            'rating_value' => data_get($product, 'rating_value'),
            'rating_count' => data_get($product, 'rating_count'),
            'url' => data_get($product, 'url'),
            'image_url' => data_get($product, 'image_url'),
            'last_checked_at' => null,
        ];
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('Free-text search: product name, series (e.g. BILLY), type or item number.'),
            'market' => $schema->string()->description('ISO country code, e.g. "us", "no", "de". Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code valid for the market, e.g. "en", "no". Defaults to the configured language.'),
            'category_id' => $schema->string()->description('Restrict to an IKEA category id (see list_categories).'),
            'product_type' => $schema->string()->description('Filter on product type, e.g. "bookcase".'),
            'min_price' => $schema->number()->description('Minimum price in the market currency.'),
            'max_price' => $schema->number()->description('Maximum price in the market currency.'),
            'include_discontinued' => $schema->boolean()->description('Include products no longer active in the market. Default false.'),
            'per_page' => $schema->integer()->min(1)->max((int) config('ikea.max_page_size'))->description('Maximum products to return directly from IKEA (default 50).'),
        ];
    }
}
