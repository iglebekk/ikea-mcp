<?php

namespace App\Mcp\Tools\Concerns;

use App\Exceptions\IkeaException;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Closure;
use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Shared behavior for all IKEA MCP tools: market/language resolution, the
 * response cache in front of the database, and the consistent JSON envelope.
 */
trait InteractsWithCatalog
{
    /**
     * Resolve and validate market/language from the request, with config defaults.
     *
     * @return array{0: string, 1: string}
     */
    protected function marketLanguage(Request $request): array
    {
        $market = strtolower($request->string('market', config('ikea.default_market'))->value());
        $language = strtolower($request->string('language', config('ikea.default_language'))->value());

        app(IkeaApi::class)->validateMarket($market, $language);

        return [$market, $language];
    }

    /**
     * Serve a payload through the response cache. The market's catalog version
     * is part of the key, so imports invalidate cached responses implicitly.
     *
     * @param  array<string, mixed>  $params
     * @param  Closure(): array<string, mixed>  $callback  returns the envelope payload (at least a "data" key)
     */
    protected function cached(string $tool, string $market, string $language, array $params, string $ttlKey, Closure $callback): Response
    {
        $version = IkeaImporter::catalogVersion($market);
        $key = "ikea:mcp:{$tool}:{$market}:{$language}:v{$version}:".md5(json_encode($params));
        $fromCache = Cache::has($key);

        $payload = Cache::remember(
            $key,
            config("ikea.cache_ttl.{$ttlKey}", 300),
            fn (): array => [...$callback(), 'fetched_at' => now()->toIso8601String()],
        );

        return $this->envelope($market, $language, [...$payload, 'from_cache' => $fromCache]);
    }

    /**
     * Wrap a payload in the consistent response envelope.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function envelope(string $market, string $language, array $payload): Response
    {
        return Response::json([
            'market' => $market,
            'language' => $language,
            'source' => 'local_catalog',
            'fetched_at' => now()->toIso8601String(),
            'from_cache' => false,
            'possibly_stale' => false,
            'warnings' => [],
            ...$payload,
        ]);
    }

    /** Map a domain exception to a clean MCP error response. */
    protected function ikeaError(IkeaException $exception): Response
    {
        return Response::error("[{$exception->reason}] {$exception->getMessage()}");
    }
}
