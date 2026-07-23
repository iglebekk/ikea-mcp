<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CompareProductsTool;
use App\Mcp\Tools\GetProductAvailabilityTool;
use App\Mcp\Tools\GetProductDocumentsTool;
use App\Mcp\Tools\GetProductTool;
use App\Mcp\Tools\GetProductVariantsTool;
use App\Mcp\Tools\ListCategoriesTool;
use App\Mcp\Tools\ListMarketsTool;
use App\Mcp\Tools\ListProductsByCategoryTool;
use App\Mcp\Tools\RefreshProductTool;
use App\Mcp\Tools\SearchProductsTool;
use Laravel\Mcp\Server;

class IkeaServer extends Server
{
    protected string $name = 'IKEA Product Catalog';

    protected string $version = '1.0.0';

    protected string $instructions = <<<'MARKDOWN'
    Detailed product information for products sold on IKEA.com, served from a
    local catalog database that is synchronized from IKEA in a controlled way.

    All tools accept optional `market` (ISO country code, e.g. "us", "no", "de")
    and `language` parameters; use list_markets to discover valid combinations.
    Search and listings read only from the local catalog. get_product fetches a
    missing product from IKEA on demand; get_product_availability fetches fresh
    stock data when the cached status is too old. Responses include provenance
    (source, fetched_at, last_checked_at, from_cache, possibly_stale, warnings).

    This is an unofficial integration and is not endorsed by IKEA.
    MARKDOWN;

    protected array $tools = [
        ListMarketsTool::class,
        SearchProductsTool::class,
        ListCategoriesTool::class,
        ListProductsByCategoryTool::class,
        GetProductTool::class,
        GetProductVariantsTool::class,
        GetProductDocumentsTool::class,
        GetProductAvailabilityTool::class,
        CompareProductsTool::class,
        RefreshProductTool::class,
    ];
}
