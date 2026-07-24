<?php

namespace App\Services;

use App\Exceptions\IkeaException;
use App\Models\Market;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * All HTTP calls against IKEA.com's unofficial endpoints. Every response is
 * normalized to plain arrays here so the rest of the app never sees raw IKEA
 * payloads. See docs/datakilder.md for the endpoint survey.
 */
class IkeaApi
{
    /**
     * Normalize an item number to its 8-digit form.
     * Accepts "002.638.50", "002-638-50", "00263850" and "s49903093" (SPR prefix).
     */
    public static function normalizeItemNo(string $input): string
    {
        $digits = preg_replace('/\D/', '', Str::of($input)->trim()->ltrim('sS')->value());

        if (strlen($digits) !== 8) {
            throw new IkeaException(
                IkeaException::INVALID_ITEM_NO,
                "Invalid IKEA item number \"{$input}\". Expected 8 digits, e.g. \"00263850\" or \"002.638.50\".",
            );
        }

        return $digits;
    }

    /** Validate a market/language combination against the markets table. */
    public function validateMarket(string $market, string $language): Market
    {
        $record = Market::query()->where('country', strtolower($market))->where('is_active', true)->first();

        if ($record === null) {
            throw new IkeaException(
                IkeaException::MARKET_UNSUPPORTED,
                "Market \"{$market}\" is not a supported IKEA market. Use the list_markets tool to see supported markets.",
            );
        }

        if (! $record->supportsLanguage($language)) {
            throw new IkeaException(
                IkeaException::LANGUAGE_UNSUPPORTED,
                "Language \"{$language}\" is not available for market \"{$market}\". Available: ".implode(', ', $record->languages).'.',
            );
        }

        return $record;
    }

    /**
     * Free-text product search. Returns normalized product cards.
     *
     * @return array{products: array<int, array<string, mixed>>, total: int|null}
     */
    public function searchProducts(string $market, string $language, string $query, int $size = 24, ?string $categoryId = null): array
    {
        $json = $this->getJson($this->searchUrl($market, $language, [
            'types' => 'PRODUCT',
            'q' => $query,
            ...(filled($categoryId) ? ['category' => $categoryId] : []),
            'size' => $size,
        ]), $market, $language);

        return $this->parseSearchResults($json);
    }

    /**
     * Products belonging to an IKEA category id. Returns normalized product cards.
     *
     * @return array{products: array<int, array<string, mixed>>, total: int|null}
     */
    public function categoryProducts(string $market, string $language, string $categoryId, int $size = 24, int $page = 1): array
    {
        $json = $this->getJson($this->searchUrl($market, $language, [
            'types' => 'PRODUCT',
            'category' => $categoryId,
            'size' => $size,
        ]), $market, $language);

        return $this->parseSearchResults($json);
    }

    /**
     * Detailed product information from the PIP JSON endpoint.
     *
     * @return array<string, mixed>
     */
    public function productDetails(string $market, string $language, string $itemNo): array
    {
        $itemNo = self::normalizeItemNo($itemNo);
        $web = rtrim(config('ikea.hosts.web'), '/');
        $url = "{$web}/{$market}/{$language}/products/".substr($itemNo, -3)."/{$itemNo}.json";

        $json = $this->getJson($url, $market, $language);

        if (! is_array($json) || $json === []) {
            throw new IkeaException(IkeaException::SCHEMA_CHANGED, "IKEA product endpoint returned an unexpected response for item {$itemNo}.");
        }

        return $this->parseProductDetails($json, $itemNo);
    }

    /**
     * Stock availability per store for up to 50 item numbers.
     *
     * @param  array<int, string>  $itemNos
     * @return array<int, array<string, mixed>>
     */
    public function availability(string $market, string $language, array $itemNos): array
    {
        $itemNos = array_map(self::normalizeItemNo(...), $itemNos);
        $host = rtrim(config('ikea.hosts.availability'), '/');
        $url = "{$host}/cia/availabilities/ru/{$market}?".http_build_query([
            'itemNos' => implode(',', $itemNos),
            'expand' => 'StoresList,Restocks,SalesLocations',
        ]);

        $json = $this->getJson($url, $market, $language, ['X-Client-Id' => config('ikea.availability_client_id')]);

        $entries = data_get($json, 'availabilities');

        if (! is_array($entries)) {
            throw new IkeaException(IkeaException::SCHEMA_CHANGED, 'IKEA availability endpoint returned an unexpected response format.');
        }

        return collect($entries)->map(fn (array $entry): array => [
            'item_no' => (string) data_get($entry, 'itemKey.itemNo'),
            'class_unit_code' => data_get($entry, 'classUnitKey.classUnitCode'),
            'class_unit_type' => data_get($entry, 'classUnitKey.classUnitType'),
            'available_for_cash_carry' => data_get($entry, 'availableForCashCarry'),
            'available_for_click_collect' => data_get($entry, 'availableForClickCollect'),
            'quantity' => data_get($entry, 'buyingOption.cashCarry.availability.quantity'),
            'probability' => data_get($entry, 'buyingOption.cashCarry.availability.probability.thisDay.messageType'),
            'home_delivery' => data_get($entry, 'buyingOption.homeDelivery.availability.probability.thisDay.messageType'),
            'restocks' => data_get($entry, 'buyingOption.cashCarry.availability.restocks', []),
        ])->all();
    }

    /**
     * Physical stores for a market, from IKEA's navigation metadata.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stores(string $market, string $language): array
    {
        $web = rtrim(config('ikea.hosts.web'), '/');
        $json = $this->getJson("{$web}/{$market}/{$language}/meta-data/navigation/stores-detailed.json", $market, $language);

        if (! is_array($json)) {
            throw new IkeaException(IkeaException::SCHEMA_CHANGED, 'IKEA stores endpoint returned an unexpected response format.');
        }

        return collect($json)->map(fn (array $store): array => [
            'id' => (string) data_get($store, 'id'),
            'name' => data_get($store, 'name'),
            'city' => data_get($store, 'address.city', data_get($store, 'displayName')),
            'address' => data_get($store, 'address.displayAddress'),
            'buy_online' => data_get($store, 'buyOnline'),
        ])->all();
    }

    /**
     * Build the search-result-page URL used for both free-text and category queries.
     *
     * @param  array<string, mixed>  $params
     */
    private function searchUrl(string $market, string $language, array $params): string
    {
        $host = rtrim(config('ikea.hosts.search'), '/');

        return "{$host}/{$market}/{$language}/search-result-page?".http_build_query($params);
    }

    /**
     * @return array{products: array<int, array<string, mixed>>, total: int|null}
     */
    private function parseSearchResults(mixed $json): array
    {
        $items = data_get($json, 'searchResultPage.products.main.items');

        if (! is_array($items)) {
            throw new IkeaException(IkeaException::SCHEMA_CHANGED, 'IKEA search endpoint returned an unexpected response format.');
        }

        $products = collect($items)
            ->map(fn (array $item): ?array => $this->parseSearchCard(data_get($item, 'product', $item)))
            ->filter()
            ->values()
            ->all();

        return [
            'products' => $products,
            'total' => data_get($json, 'searchResultPage.products.main.totalCount', data_get($json, 'searchResultPage.products.main.max')),
        ];
    }

    /**
     * Normalize one search-result product card. Tolerates missing fields.
     *
     * @return array<string, mixed>|null
     */
    private function parseSearchCard(mixed $card): ?array
    {
        if (! is_array($card)) {
            return null;
        }

        $itemNo = preg_replace('/\D/', '', (string) (data_get($card, 'itemNoGlobal') ?: data_get($card, 'itemNo', '')));

        if (strlen($itemNo) !== 8) {
            return null;
        }

        return [
            'item_no' => $itemNo,
            'name' => data_get($card, 'name'),
            'type_name' => data_get($card, 'typeName'),
            'description' => data_get($card, 'itemMeasureReferenceText', data_get($card, 'mainImageAlt')),
            'price' => data_get($card, 'salesPrice.numeral', data_get($card, 'priceNumeral')),
            'regular_price' => data_get($card, 'salesPrice.previous.numeral'),
            'currency' => data_get($card, 'currencyCode', data_get($card, 'salesPrice.currencyCode')),
            'url' => data_get($card, 'pipUrl'),
            'image_url' => data_get($card, 'mainImageUrl'),
            'rating_value' => data_get($card, 'ratingValue'),
            'rating_count' => data_get($card, 'ratingCount'),
            'online_sellable' => data_get($card, 'onlineSellable'),
            'last_chance' => data_get($card, 'lastChance'),
            'colors' => collect(data_get($card, 'colors', []))->pluck('name')->filter()->values()->all(),
            'category_path' => collect(data_get($card, 'categoryPath', []))->map(fn ($c) => [
                'id' => data_get($c, 'key', data_get($c, 'id')),
                'name' => data_get($c, 'name'),
            ])->all(),
            'variants' => collect(data_get($card, 'gprDescription.variants', []))
                ->map(fn ($v) => preg_replace('/\D/', '', (string) data_get($v, 'itemNoGlobal', data_get($v, 'itemNo', ''))))
                ->filter(fn (string $no) => strlen($no) === 8)
                ->values()
                ->all(),
        ];
    }

    /**
     * Normalize the PIP product JSON. Field names vary between markets and over
     * time, so every lookup is defensive with fallbacks.
     *
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function parseProductDetails(array $json, string $itemNo): array
    {
        return [
            'item_no' => $itemNo,
            'name' => data_get($json, 'name'),
            'type_name' => data_get($json, 'typeName'),
            'description' => data_get($json, 'pipDescription', data_get($json, 'productDescription', data_get($json, 'description'))),
            'benefits' => data_get($json, 'benefits', []),
            'materials' => data_get($json, 'materials', data_get($json, 'materialsAndCare.materials', [])),
            'care_instructions' => data_get($json, 'careInstructions', data_get($json, 'materialsAndCare.care', [])),
            'safety_information' => data_get($json, 'goodToKnows', data_get($json, 'safetyAndCompliance', [])),
            'technical_details' => data_get($json, 'technicalInformation', data_get($json, 'attributes', [])),
            'measurements' => data_get($json, 'dimensionProps.dimensions', data_get($json, 'measurements', [])),
            'packages' => data_get($json, 'packageProps.packages', data_get($json, 'packages', [])),
            'price' => data_get($json, 'priceNumeral', data_get($json, 'salesPrice.numeral')),
            'currency' => data_get($json, 'currencyCode', data_get($json, 'salesPrice.currencyCode')),
            'url' => data_get($json, 'pipUrl'),
            'images' => collect(data_get($json, 'images', []))
                ->map(fn ($img) => is_string($img) ? $img : data_get($img, 'url', data_get($img, 'imageUrl')))
                ->filter()
                ->values()
                ->all(),
            'documents' => collect(data_get($json, 'attachments', data_get($json, 'documents', [])))
                ->map(fn ($doc) => [
                    'type' => data_get($doc, 'type', 'document'),
                    'title' => data_get($doc, 'name', data_get($doc, 'title')),
                    'url' => data_get($doc, 'url', data_get($doc, 'href')),
                ])
                ->filter(fn (array $doc) => filled($doc['url']))
                ->values()
                ->all(),
            'variants' => collect(data_get($json, 'styleGroupPrimaryProducts', data_get($json, 'variants', [])))
                ->map(fn ($v) => is_string($v) ? preg_replace('/\D/', '', $v) : preg_replace('/\D/', '', (string) data_get($v, 'itemNo', '')))
                ->filter(fn (string $no) => strlen($no) === 8)
                ->values()
                ->all(),
        ];
    }

    /**
     * Perform a rate-limited GET returning decoded JSON, mapping HTTP failures
     * to the IkeaException taxonomy. Headers are built per host/market so that
     * bot-protected endpoints (www.ikea.com, api.ingka.ikea.com) receive the
     * storefront context (Accept-Language, Referer, Origin) they require; the
     * search CDN accepts requests without it, which is why search worked while
     * product details and stock were rejected with HTTP 403.
     *
     * @param  array<string, string>  $extraHeaders
     */
    private function getJson(string $url, string $market, string $language, array $extraHeaders = []): mixed
    {
        $this->throttle($this->scopeFor($url, $market));

        $headers = $this->headersFor($url, $market, $language, $extraHeaders);

        Log::debug('ikea.request', [
            'method' => 'GET',
            'host' => parse_url($url, PHP_URL_HOST),
            'path' => parse_url($url, PHP_URL_PATH),
            'market' => $market,
            'language' => $language,
            // Header names only — never values — so no client ids or cookies leak.
            'headers' => array_keys($headers),
        ]);

        try {
            $response = $this->client($headers)->get($url);
        } catch (ConnectionException $e) {
            throw new IkeaException(IkeaException::TEMPORARY, "Could not reach IKEA ({$e->getMessage()}).");
        }

        Log::debug('ikea.response', [
            'host' => parse_url($url, PHP_URL_HOST),
            'market' => $market,
            'status' => $response->status(),
        ]);

        return $this->decode($response, $url);
    }

    /**
     * Build the request headers for a given endpoint. www.ikea.com and the
     * Ingka availability API sit behind bot protection that rejects requests
     * lacking a storefront Accept-Language/Referer (and, cross-origin, Origin).
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function headersFor(string $url, string $market, string $language, array $extra = []): array
    {
        $web = rtrim(config('ikea.hosts.web'), '/');

        $headers = [
            'User-Agent' => config('ikea.user_agent'),
            'Accept' => 'application/json',
            'Accept-Language' => $this->acceptLanguage($market, $language),
            'Referer' => "{$web}/{$market}/".($language !== '' ? "{$language}/" : ''),
        ];

        if (parse_url($url, PHP_URL_HOST) === parse_url((string) config('ikea.hosts.availability'), PHP_URL_HOST)) {
            $headers['Origin'] = $web;
        }

        return array_merge($headers, $extra);
    }

    /**
     * Build a browser-like Accept-Language value, e.g. "no-NO,no;q=0.9,en;q=0.8".
     * The English fallback is omitted when the language is already English so we
     * never emit a duplicated "en" entry (e.g. "en-US,en;q=0.9").
     */
    private function acceptLanguage(string $market, string $language): string
    {
        $language = $language !== '' ? strtolower($language) : 'en';
        $value = "{$language}-".strtoupper($market).",{$language};q=0.9";

        return $language === 'en' ? $value : "{$value},en;q=0.8";
    }

    /**
     * Rate-limit scope for a request: per host and market, so a burst or block
     * on one endpoint/market never starves requests to another.
     */
    private function scopeFor(string $url, string $market): string
    {
        return parse_url($url, PHP_URL_HOST).':'.$market;
    }

    private function client(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers)
            ->timeout(config('ikea.timeout'))
            ->retry(
                config('ikea.retries'),
                fn (int $attempt): int => $attempt * 500 + random_int(0, 250),
                fn (\Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
    }

    private function throttle(string $scope): void
    {
        $allowed = RateLimiter::attempt(
            "ikea-upstream:{$scope}",
            config('ikea.requests_per_minute'),
            fn () => true,
        );

        if (! $allowed) {
            throw new IkeaException(
                IkeaException::RATE_LIMITED,
                'Local rate limit towards IKEA reached. Try again in a minute.',
            );
        }
    }

    private function decode(Response $response, string $url): mixed
    {
        if ($response->status() === 404) {
            throw new IkeaException(IkeaException::NOT_FOUND, 'IKEA has no data at this address. The product or category may not exist in this market.');
        }

        if (in_array($response->status(), [403, 429], true)) {
            $reason = $response->status() === 429 ? IkeaException::RATE_LIMITED : IkeaException::BLOCKED;

            throw new IkeaException($reason, "IKEA rejected the request (HTTP {$response->status()}). Backing off.");
        }

        if ($response->failed()) {
            throw new IkeaException(IkeaException::TEMPORARY, "IKEA responded with HTTP {$response->status()}.");
        }

        $json = $response->json();

        if ($json === null && ! Str::of($response->body())->trim()->exactly('null')) {
            throw new IkeaException(IkeaException::SCHEMA_CHANGED, "IKEA returned a non-JSON response from {$url}.");
        }

        return $json;
    }
}
