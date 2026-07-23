<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Http\Resources\ProductSummaryResource;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Product;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Search IKEA products in the local catalog by free text, with filters for category, price range, product type and status. Reads only from the local database; run ikea:sync or get_product to bring products in.')]
class SearchProductsTool extends Tool
{
    use InteractsWithCatalog;

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
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('ikea.max_page_size')],
        ]);

        return $this->cached('search_products', $market, $language, $validated, 'search', function () use ($validated, $market, $language): array {
            $term = trim((string) data_get($validated, 'query', ''));

            $results = Product::query()
                ->forMarket($market, $language)
                ->with('assets')
                ->when($term !== '', fn (Builder $q) => $q->where(fn (Builder $group) => $group
                    ->whereHas('translations', fn (Builder $t) => $t
                        ->where('language', $language)
                        ->where(fn (Builder $w) => $w
                            ->whereLike('name', "%{$term}%")
                            ->orWhereLike('type_name', "%{$term}%")
                            ->orWhereLike('description', "%{$term}%")))
                    ->orWhere('item_no', preg_replace('/\D/', '', $term) ?: $term)
                    ->orWhereLike('series', "%{$term}%")))
                ->when(data_get($validated, 'category_id'), fn (Builder $q, string $categoryId) => $q
                    ->whereHas('categories', fn (Builder $c) => $c->where('ikea_id', $categoryId)->where('market', $market)))
                ->when(data_get($validated, 'product_type'), fn (Builder $q, string $type) => $q
                    ->whereLike('product_type', "%{$type}%"))
                ->when(data_get($validated, 'min_price'), fn (Builder $q, $min) => $q
                    ->whereHas('marketProducts', fn (Builder $m) => $m->where('market', $market)->where('price', '>=', $min)))
                ->when(data_get($validated, 'max_price'), fn (Builder $q, $max) => $q
                    ->whereHas('marketProducts', fn (Builder $m) => $m->where('market', $market)->where('price', '<=', $max)))
                ->when(! data_get($validated, 'include_discontinued', false), fn (Builder $q) => $q
                    ->whereHas('marketProducts', fn (Builder $m) => $m->where('market', $market)->where('status', 'active')))
                ->orderBy('item_no')
                ->paginate(
                    perPage: (int) data_get($validated, 'per_page', 10),
                    page: (int) data_get($validated, 'page', 1),
                );

            return [
                'data' => ProductSummaryResource::collection($results->items())->resolve(),
                'pagination' => [
                    'page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
                'warnings' => $results->total() === 0 ? [
                    'No products matched in the local catalog. The catalog only contains synchronized products; run ikea:sync for this market or look up a specific product with get_product.',
                ] : [],
            ];
        });
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
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
            'per_page' => $schema->integer()->min(1)->max((int) config('ikea.max_page_size'))->description('Results per page (default 10).'),
        ];
    }
}
