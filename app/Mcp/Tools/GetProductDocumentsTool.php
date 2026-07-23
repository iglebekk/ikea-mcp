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
#[Description('Get document and image metadata for an IKEA product (assembly instructions, manuals, safety documents, images) as source URLs from the local catalog.')]
class GetProductDocumentsTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'get_product_documents';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate(['item_number' => ['required', 'string', 'max:255']]);
            $itemNo = IkeaApi::normalizeItemNo($validated['item_number']);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }

        return $this->cached('get_product_documents', $market, $language, ['item_no' => $itemNo], 'documents', function () use ($itemNo): array {
            $product = Product::query()->where('item_no', $itemNo)->with('assets')->first();

            if ($product === null) {
                return [
                    'data' => [],
                    'warnings' => ["Product {$itemNo} is not in the local catalog. Call get_product first to import it."],
                ];
            }

            return [
                'data' => $product->assets->map(fn ($asset): array => [
                    'type' => $asset->type,
                    'title' => $asset->title,
                    'url' => $asset->url,
                    'language' => $asset->language,
                ])->all(),
                'warnings' => $product->assets->isEmpty()
                    ? ['No assets stored for this product yet. Use refresh_product to re-fetch details from IKEA.']
                    : [],
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
