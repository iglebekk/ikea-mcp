<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Market;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the IKEA markets (countries) and languages this server supports, with currencies and the configured defaults.')]
class ListMarketsTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'list_markets';

    public function handle(Request $request): Response
    {
        return $this->cached('list_markets', 'global', 'all', [], 'markets', fn (): array => [
            'data' => Market::query()->where('is_active', true)->orderBy('country')->get()
                ->map(fn (Market $market): array => [
                    'country' => $market->country,
                    'name' => $market->name,
                    'languages' => $market->languages,
                    'currency' => $market->currency,
                ])->all(),
            'defaults' => [
                'market' => config('ikea.default_market'),
                'language' => config('ikea.default_language'),
            ],
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
