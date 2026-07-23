<?php

namespace App\Mcp\Tools;

use App\Exceptions\IkeaException;
use App\Mcp\Tools\Concerns\InteractsWithCatalog;
use App\Models\Product;
use App\Models\StockStatus;
use App\Services\IkeaApi;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[Description('Get stock availability for an IKEA product per store in a market, including restock dates. Fetches fresh data from IKEA when the cached status is older than the configured freshness window.')]
class GetProductAvailabilityTool extends Tool
{
    use InteractsWithCatalog;

    public function __construct(public IkeaApi $api) {}

    protected string $name = 'get_product_availability';

    public function handle(Request $request): Response
    {
        try {
            [$market, $language] = $this->marketLanguage($request);

            $validated = $request->validate([
                'item_number' => ['required', 'string', 'max:255'],
                'store_id' => ['nullable', 'string', 'max:20'],
                'postal_code' => ['nullable', 'string', 'max:20'],
            ]);

            $itemNo = IkeaApi::normalizeItemNo($validated['item_number']);

            $product = Product::query()->firstOrCreate(
                ['item_no' => $itemNo],
                ['first_observed_at' => now(), 'last_observed_at' => now()],
            );

            $statuses = $product->stockStatuses()->where('market', $market)->get();
            $maxAge = now()->subSeconds(config('ikea.availability_max_age'));
            $possiblyStale = false;
            $warnings = [];

            if ($statuses->isEmpty() || $statuses->min('checked_at')?->lt($maxAge)) {
                try {
                    $statuses = $this->refreshFromIkea($product, $market, $language);
                } catch (IkeaException $e) {
                    $canFallBack = $statuses->isNotEmpty()
                        && ($e->isTemporary() || $e->reason === IkeaException::BLOCKED);

                    if (! $canFallBack) {
                        throw $this->blockedStockError($e, $itemNo, $market);
                    }

                    $possiblyStale = true;
                    $warnings[] = $e->reason === IkeaException::BLOCKED
                        ? "IKEA is currently blocking automated stock lookups ({$e->reason}); returning the last known stock status."
                        : "IKEA could not be reached ({$e->reason}); returning the last known stock status.";
                }
            }

            if (filled(data_get($validated, 'store_id'))) {
                $statuses = $statuses->where('store_id', $validated['store_id'])->values();
            }

            if (filled(data_get($validated, 'postal_code'))) {
                $warnings[] = 'Postal code filtering is not supported yet; returning market-level and per-store availability. Use list_stores to find your nearest store.';
            }

            return $this->envelope($market, $language, [
                'data' => $statuses->map(fn (StockStatus $status): array => [
                    'store_id' => $status->store_id,
                    'store_name' => $status->store_name,
                    'scope' => $status->store_id === null ? 'market' : 'store',
                    'quantity' => $status->quantity,
                    'probability' => $status->probability,
                    'restock_expected_at' => $status->restock_expected_at?->toDateString(),
                    'checked_at' => $status->checked_at->toIso8601String(),
                ])->values()->all(),
                'source' => $possiblyStale ? 'local_catalog' : 'ikea_live',
                'last_checked_at' => $statuses->min('checked_at')?->toIso8601String(),
                'possibly_stale' => $possiblyStale,
                'warnings' => $warnings,
            ]);
        } catch (IkeaException $e) {
            return $this->ikeaError($e);
        }
    }

    /**
     * @return Collection<int, StockStatus>
     */
    private function refreshFromIkea(Product $product, string $market, string $language): Collection
    {
        $entries = $this->api->availability($market, $language, [$product->item_no]);
        $stores = collect($this->storesFor($market, $language))->keyBy('id');

        $product->stockStatuses()->where('market', $market)->delete();

        foreach ($entries as $entry) {
            $isStore = data_get($entry, 'class_unit_type') === 'STO';

            $product->stockStatuses()->create([
                'market' => $market,
                'store_id' => $isStore ? data_get($entry, 'class_unit_code') : null,
                'store_name' => $isStore ? data_get($stores, data_get($entry, 'class_unit_code').'.name') : null,
                'quantity' => data_get($entry, 'quantity'),
                'probability' => data_get($entry, 'probability'),
                'restock_expected_at' => data_get($entry, 'restocks.0.earliestDate'),
                'checked_at' => now(),
                'meta' => [
                    'home_delivery' => data_get($entry, 'home_delivery'),
                    'available_for_cash_carry' => data_get($entry, 'available_for_cash_carry'),
                ],
            ]);
        }

        return $product->stockStatuses()->where('market', $market)->get();
    }

    /**
     * Turn an upstream failure with no local stock to fall back on into a clear,
     * stock-specific error instead of a bare "HTTP 403" so callers know exactly
     * what is blocked (and that product details are unaffected).
     */
    private function blockedStockError(IkeaException $exception, string $itemNo, string $market): IkeaException
    {
        if ($exception->reason !== IkeaException::BLOCKED) {
            return $exception;
        }

        return new IkeaException(
            IkeaException::BLOCKED,
            "IKEA is currently blocking automated stock lookups for item {$itemNo} in market {$market} (HTTP 403), and no recent stock is cached. Product details from get_product are unaffected; try stock again later.",
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function storesFor(string $market, string $language): array
    {
        try {
            return cache()->remember(
                "ikea:stores:{$market}",
                config('ikea.cache_ttl.markets'),
                fn (): array => $this->api->stores($market, $language),
            );
        } catch (IkeaException) {
            return [];
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
            'store_id' => $schema->string()->description('Limit the result to one store id (see list_stores via availability data).'),
            'postal_code' => $schema->string()->description('Postal code (not yet used for filtering; included for future delivery lookups).'),
        ];
    }
}
