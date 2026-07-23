<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
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
#[Description('List categories observed for individually cached IKEA products. This is not a complete live IKEA category tree; use search_products for product discovery.')]
class ListCategoriesTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'list_categories';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        return $this->cached('list_categories', $market, $language, [], 'categories', function () use ($market, $language): array {
            $categories = Category::query()
                ->where('market', $market)
                ->where('language', $language)
                ->where('is_active', true)
                ->withCount('products')
                ->orderBy('name')
                ->get();

            return [
                'data' => $categories->map(fn (Category $category): array => [
                    'id' => $category->ikea_id,
                    'name' => $category->name,
                    'parent_id' => $categories->firstWhere('id', $category->parent_id)?->ikea_id,
                    'product_count' => $category->products_count,
                ])->all(),
                'warnings' => $categories->isEmpty() ? [
                    "No categories are cached for {$market}/{$language}. This does not affect live product searches; use search_products to query IKEA directly.",
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
            'market' => $schema->string()->description('ISO country code, e.g. "us", "no". Defaults to the configured market.'),
            'language' => $schema->string()->description('Language code valid for the market. Defaults to the configured language.'),
        ];
    }
}
