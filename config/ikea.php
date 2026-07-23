<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default market and language
    |--------------------------------------------------------------------------
    |
    | Used when an MCP call does not specify a market/language. Any market in
    | the markets table can be requested per call regardless of this default.
    |
    */

    'default_market' => env('IKEA_MARKET', 'us'),
    'default_language' => env('IKEA_LANGUAGE', 'en'),

    /*
    |--------------------------------------------------------------------------
    | Upstream endpoints
    |--------------------------------------------------------------------------
    |
    | Unofficial endpoints used by IKEA.com's own storefront. They can change
    | without notice; see docs/datakilder.md. The availability client id is a
    | public identifier used by IKEA's own web frontend, not a secret.
    |
    */

    'hosts' => [
        'search' => env('IKEA_SEARCH_HOST', 'https://sik.search.blue.cdtapps.com'),
        'web' => env('IKEA_WEB_HOST', 'https://www.ikea.com'),
        'availability' => env('IKEA_AVAILABILITY_HOST', 'https://api.ingka.ikea.com'),
    ],

    'availability_client_id' => env('IKEA_AVAILABILITY_CLIENT_ID', 'b6c117e5-ae61-4ef5-b4cc-e0b1e37f0631'),

    'user_agent' => env('IKEA_USER_AGENT', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'),

    /*
    |--------------------------------------------------------------------------
    | Politeness towards IKEA
    |--------------------------------------------------------------------------
    */

    'timeout' => (int) env('IKEA_HTTP_TIMEOUT', 20),
    'retries' => (int) env('IKEA_HTTP_RETRIES', 3),
    'requests_per_minute' => (int) env('IKEA_REQUESTS_PER_MINUTE', 30),

    /*
    |--------------------------------------------------------------------------
    | Response cache TTLs (seconds) per data type
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => [
        'markets' => (int) env('IKEA_TTL_MARKETS', 86400),
        'categories' => (int) env('IKEA_TTL_CATEGORIES', 43200),
        'search' => (int) env('IKEA_TTL_SEARCH', 1800),
        'category_products' => (int) env('IKEA_TTL_CATEGORY_PRODUCTS', 3600),
        'product' => (int) env('IKEA_TTL_PRODUCT', 21600),
        'variants' => (int) env('IKEA_TTL_VARIANTS', 21600),
        'documents' => (int) env('IKEA_TTL_DOCUMENTS', 21600),
        'compare' => (int) env('IKEA_TTL_COMPARE', 3600),
        'availability' => (int) env('IKEA_TTL_AVAILABILITY', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Freshness windows and limits
    |--------------------------------------------------------------------------
    */

    'availability_max_age' => (int) env('IKEA_AVAILABILITY_MAX_AGE', 300),
    'product_stale_after_days' => (int) env('IKEA_PRODUCT_STALE_AFTER_DAYS', 7),
    'refresh_per_minute' => (int) env('IKEA_REFRESH_PER_MINUTE', 5),
    'max_page_size' => (int) env('IKEA_MAX_PAGE_SIZE', 50),

    'sync' => [
        'page_size' => (int) env('IKEA_SYNC_PAGE_SIZE', 50),
        'max_pages' => (int) env('IKEA_SYNC_MAX_PAGES', 20),
    ],
];
