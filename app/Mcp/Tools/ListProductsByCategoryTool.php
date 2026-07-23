<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Http\Resources\ProductSummaryResource;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Category;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List products in an IKEA category from the local catalog, paginated.')]
class ListProductsByCategoryTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'list_products_by_category';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        $validated = $request->validate([
            'category_id' => ['required', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.config('ikea.max_page_size')],
        ]);

        return $this->cached('list_products_by_category', $market, $language, $validated, 'category_products', function () use ($validated, $market, $language): array {
            $category = Category::query()
                ->where('market', $market)
                ->where('ikea_id', $validated['category_id'])
                ->first();

            if ($category === null) {
                return [
                    'data' => [],
                    'warnings' => ["Category {$validated['category_id']} is not in the local catalog for market {$market}. Use list_categories, or run ikea:sync."],
                ];
            }

            $results = $category->products()
                ->forMarket($market, $language)
                ->with('assets')
                ->orderBy('item_no')
                ->paginate(
                    perPage: (int) data_get($validated, 'per_page', 10),
                    page: (int) data_get($validated, 'page', 1),
                );

            return [
                'data' => ProductSummaryResource::collection($results->items())->resolve(),
                'category' => ['id' => $category->ikea_id, 'name' => $category->name],
                'pagination' => [
                    'page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
            ];
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'category_id' => $schema->string()->description('IKEA category id from list_categories.')->required(),
            'market' => $schema->string()->description('ISO country code. Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code. Defaults to the configured language.'),
            'page' => $schema->integer()->min(1)->description('Page number, starting at 1.'),
            'per_page' => $schema->integer()->min(1)->max((int) config('ikea.max_page_size'))->description('Results per page (default 10).'),
        ];
    }
}
