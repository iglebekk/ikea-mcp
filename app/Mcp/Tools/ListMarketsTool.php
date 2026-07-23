<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Market;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('List the IKEA markets (countries) and languages this server supports, with currencies and the authenticated user\'s configured defaults.')]
class ListMarketsTool extends Tool
{
    use InteractsWithCatalog;

    protected string $name = 'list_markets';

    public function handle(Request $request): Response
    {
        $markets = Cache::remember(
            'ikea:mcp:list_markets:v1',
            config('ikea.cache_ttl.markets', 300),
            fn (): array => Market::query()->where('is_active', true)->orderBy('country')->get()
                ->map(fn (Market $market): array => [
                    'country' => $market->country,
                    'name' => $market->name,
                    'languages' => $market->languages,
                    'currency' => $market->currency,
                ])->all(),
        );

        return $this->envelope('global', 'all', [
            'data' => $markets,
            'defaults' => [
                'market' => $this->defaultMarket($request),
                'language' => $this->defaultLanguage($request),
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
