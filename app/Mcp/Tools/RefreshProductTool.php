<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Force a re-check of one product against IKEA and update the local catalog. Rate limited; use only when data looks outdated.')]
class RefreshProductTool extends Tool
{
    use InteractsWithCatalog;

    public function __construct(public IkeaApi $api, public IkeaImporter $importer) {}

    protected string $name = 'refresh_product';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate(['item_number' => ['required', 'string', 'max:255']]);
            $itemNo = IkeaApi::normalizeItemNo($validated['item_number']);

            $allowed = RateLimiter::attempt(
                'ikea-refresh',
                config('ikea.refresh_per_minute'),
                fn (): bool => true,
            );

            if (! $allowed) {
                return Response::error('[rate_limited] Too many refresh requests. Wait a minute and try again.');
            }

            $details = $this->api->productDetails($market, $language, $itemNo);
            $product = $this->importer->importDetails($details, $market, $language);

            return $this->envelope($market, $language, [
                'data' => [
                    'item_no' => $product->item_no,
                    'refreshed' => true,
                    'last_checked_at' => now()->toIso8601String(),
                ],
                'source' => 'ikea_live',
            ]);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }
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
