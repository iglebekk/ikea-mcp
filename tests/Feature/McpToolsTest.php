<?php

namespace Tests\Feature;

use App\Mcp\Servers\IkeaServer;
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
use App\Models\Product;
use App\Models\StockStatus;
use App\Services\IkeaApi;
use App\Services\IkeaImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class McpToolsTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    private function importBilly(string $market = 'us', string $language = 'en', array $fakes = []): Product
    {
        $this->fakeIkea($fakes);
        $details = app(IkeaApi::class)->productDetails($market, $language, '00263850');
        $card = app(IkeaApi::class)->searchProducts($market, $language, 'billy')['products'][0];

        $importer = app(IkeaImporter::class);
        $importer->importCard($card, $market, $language);

        return $importer->importDetails($details, $market, $language);
    }

    public function test_list_markets_returns_supported_markets_and_defaults(): void
    {
        IkeaServer::tool(ListMarketsTool::class)
            ->assertOk()
            ->assertSee('"country":"no"')
            ->assertSee('"defaults"');
    }

    public function test_search_always_queries_ikea_even_when_products_exist_locally(): void
    {
        $this->importBilly();

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"')
            ->assertSee('"from_cache":false');

        Http::assertSentCount(3);
    }

    public function test_search_matches_item_numbers_and_series(): void
    {
        $this->importBilly();

        IkeaServer::tool(SearchProductsTool::class, ['query' => '002.638.50'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"');
    }

    public function test_search_fetches_from_ikea_without_importing_results(): void
    {
        $this->fakeIkea();

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'market' => 'no', 'language' => 'no'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"')
            ->assertSee('"source":"ikea_live"');

        $this->assertDatabaseMissing('market_products', ['market' => 'no']);
        $this->assertDatabaseMissing('product_translations', ['language' => 'no', 'name' => 'BILLY']);

        Http::assertSentCount(1);
    }

    public function test_search_respects_price_filters_and_pagination(): void
    {
        $this->importBilly();

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'max_price' => 50])
            ->assertOk()
            ->assertSee('"source":"ikea_live"')
            ->assertSee('"total":0');

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'min_price' => 50, 'per_page' => 1])
            ->assertOk()
            ->assertSee('"total":2')
            ->assertSee('"per_page":1');
    }

    public function test_search_returns_live_ikea_results_regardless_of_local_product_status(): void
    {
        $product = $this->importBilly();
        $product->marketProducts()->update(['status' => 'discontinued']);

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy'])
            ->assertOk()
            ->assertSee('"source":"ikea_live"')
            ->assertSee('"total":2');

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'include_discontinued' => true])
            ->assertOk()
            ->assertSee('"item_no":"00263850"');
    }

    public function test_search_does_not_persist_products_for_an_empty_market(): void
    {
        $this->importBilly('us', 'en');

        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'market' => 'no', 'language' => 'no'])
            ->assertOk()
            ->assertSee('"source":"ikea_live"')
            ->assertSee('"total":2');

        $this->assertDatabaseMissing('market_products', ['market' => 'no']);
    }

    public function test_invalid_market_returns_clean_error(): void
    {
        IkeaServer::tool(SearchProductsTool::class, ['query' => 'billy', 'market' => 'xx'])
            ->assertHasErrors()
            ->assertSee('market_unsupported');
    }

    public function test_get_product_serves_from_the_local_catalog_without_calling_ikea(): void
    {
        $this->importBilly();
        Http::fake(); // any further HTTP call would hit the empty fake

        IkeaServer::tool(GetProductTool::class, ['item_number' => '002.638.50'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"')
            ->assertSee('"source":"local_catalog"')
            ->assertSee('Adjustable shelves');

        Http::assertNothingSent();
    }

    public function test_get_product_fetches_from_ikea_when_missing_locally(): void
    {
        $this->fakeIkea();

        IkeaServer::tool(GetProductTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"source":"ikea_live"')
            ->assertSee('"name":"BILLY"');

        $this->assertDatabaseHas('products', ['item_no' => '00263850']);
    }

    public function test_get_product_falls_back_to_search_when_pip_is_blocked(): void
    {
        $this->fakeIkea([
            'www.ikea.com/*/products/*' => Http::response('denied', 403),
        ]);

        IkeaServer::tool(GetProductTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"')
            ->assertSee('"name":"BILLY"')
            ->assertSee('"source":"ikea_search_fallback"')
            ->assertSee('"possibly_stale":true')
            ->assertSee('blocked by IKEA');

        $this->assertDatabaseHas('products', ['item_no' => '00263850']);
    }

    public function test_get_product_reports_blocked_when_search_fallback_finds_nothing(): void
    {
        $this->fakeIkea([
            'www.ikea.com/*/products/*' => Http::response('denied', 403),
        ]);

        // The search fixture does not contain this item, so the fallback fails.
        IkeaServer::tool(GetProductTool::class, ['item_number' => '11111111'])
            ->assertHasErrors()
            ->assertSee('blocked');
    }

    public function test_get_product_for_unknown_item_reports_not_found(): void
    {
        Http::fake(['www.ikea.com/*' => Http::response(null, 404)]);

        IkeaServer::tool(GetProductTool::class, ['item_number' => '99999999'])
            ->assertHasErrors()
            ->assertSee('not_found');
    }

    public function test_get_product_rejects_malformed_item_numbers(): void
    {
        IkeaServer::tool(GetProductTool::class, ['item_number' => 'abc'])
            ->assertHasErrors()
            ->assertSee('invalid_item_no');
    }

    public function test_get_product_responses_are_cached_until_an_import_bumps_the_version(): void
    {
        $this->importBilly();

        IkeaServer::tool(GetProductTool::class, ['item_number' => '00263850'])->assertSee('"from_cache":false');
        IkeaServer::tool(GetProductTool::class, ['item_number' => '00263850'])->assertSee('"from_cache":true');

        app(IkeaImporter::class)->bumpCatalogVersion('us');

        IkeaServer::tool(GetProductTool::class, ['item_number' => '00263850'])->assertSee('"from_cache":false');
    }

    public function test_list_categories_returns_the_synced_tree(): void
    {
        $this->importBilly();

        IkeaServer::tool(ListCategoriesTool::class)
            ->assertOk()
            ->assertSee('"id":"10382"')
            ->assertSee('"parent_id":"bc001"');
    }

    public function test_list_products_by_category_paginates_catalog_products(): void
    {
        $this->importBilly();

        IkeaServer::tool(ListProductsByCategoryTool::class, ['category_id' => '10382'])
            ->assertOk()
            ->assertSee('"item_no":"00263850"')
            ->assertSee('"name":"Bookcases"');
    }

    public function test_list_products_by_unknown_category_warns(): void
    {
        IkeaServer::tool(ListProductsByCategoryTool::class, ['category_id' => 'nope'])
            ->assertOk()
            ->assertSee('not cached for market');
    }

    public function test_get_product_variants_includes_local_catalog_hits(): void
    {
        $this->importBilly();

        IkeaServer::tool(GetProductVariantsTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"item_no":"90404097"')
            ->assertSee('"in_local_catalog":false');
    }

    public function test_get_product_documents_lists_assets_with_urls(): void
    {
        $this->importBilly();

        IkeaServer::tool(GetProductDocumentsTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('assembly_instruction')
            ->assertSee('AA-2358346-1-100.pdf');
    }

    public function test_availability_fetches_fresh_data_and_stores_it(): void
    {
        $product = $this->importBilly();

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"source":"ikea_live"')
            ->assertSee('HIGH_IN_STOCK')
            ->assertSee('IKEA Brooklyn')
            ->assertSee('2026-08-01');

        $this->assertSame(2, $product->stockStatuses()->where('market', 'us')->count());
    }

    public function test_availability_reuses_fresh_local_data_without_calling_ikea(): void
    {
        $product = $this->importBilly();
        StockStatus::factory()->for($product)->create(['checked_at' => now()]);
        Http::fake();

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850'])
            ->assertOk();

        Http::assertNothingSent();
    }

    public function test_availability_falls_back_to_stale_data_when_ikea_is_down(): void
    {
        $product = $this->importBilly(fakes: [
            'api.ingka.ikea.com/cia/availabilities/*' => Http::response('oops', 500),
        ]);
        StockStatus::factory()->for($product)->create([
            'checked_at' => now()->subHours(2),
            'probability' => 'LOW_IN_STOCK',
        ]);

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"possibly_stale":true')
            ->assertSee('last known stock status');
    }

    public function test_availability_returns_structured_error_when_blocked_without_cache(): void
    {
        $this->importBilly(fakes: [
            'api.ingka.ikea.com/cia/availabilities/*' => Http::response('denied', 403),
        ]);

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850'])
            ->assertHasErrors()
            ->assertSee('blocking automated stock lookups')
            ->assertSee('Product details from get_product are unaffected');
    }

    public function test_availability_falls_back_to_stale_data_when_blocked(): void
    {
        $product = $this->importBilly(fakes: [
            'api.ingka.ikea.com/cia/availabilities/*' => Http::response('denied', 403),
        ]);
        StockStatus::factory()->for($product)->create([
            'checked_at' => now()->subHours(2),
            'probability' => 'LOW_IN_STOCK',
        ]);

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"possibly_stale":true')
            ->assertSee('blocking automated stock lookups');
    }

    public function test_availability_can_filter_on_store(): void
    {
        $this->importBilly();

        IkeaServer::tool(GetProductAvailabilityTool::class, ['item_number' => '00263850', 'store_id' => '088'])
            ->assertOk()
            ->assertSee('"store_id":"088"')
            ->assertDontSee('"scope":"market"');
    }

    public function test_compare_products_builds_a_structured_diff(): void
    {
        $this->importBilly();
        $this->fakeIkea();
        app(IkeaImporter::class)->importCard(
            app(IkeaApi::class)->searchProducts('us', 'en', 'billy')['products'][1],
            'us',
            'en',
        );

        IkeaServer::tool(CompareProductsTool::class, ['item_numbers' => ['00263850', '90404097']])
            ->assertOk()
            ->assertSee('"comparison"')
            ->assertSee('"differs":true');
    }

    public function test_compare_warns_about_products_missing_from_the_catalog(): void
    {
        $this->importBilly();
        $this->fakeIkea();
        app(IkeaImporter::class)->importCard(
            app(IkeaApi::class)->searchProducts('us', 'en', 'billy')['products'][1],
            'us',
            'en',
        );

        IkeaServer::tool(CompareProductsTool::class, ['item_numbers' => ['00263850', '90404097', '11111111']])
            ->assertOk()
            ->assertSee('excluded from the comparison: 11111111');
    }

    public function test_refresh_product_forces_a_recheck_against_ikea(): void
    {
        $this->fakeIkea();

        IkeaServer::tool(RefreshProductTool::class, ['item_number' => '00263850'])
            ->assertOk()
            ->assertSee('"refreshed":true');

        $this->assertDatabaseHas('products', ['item_no' => '00263850']);
    }

    public function test_refresh_product_is_rate_limited(): void
    {
        config(['ikea.refresh_per_minute' => 1]);
        $this->fakeIkea();

        IkeaServer::tool(RefreshProductTool::class, ['item_number' => '00263850'])->assertOk();

        IkeaServer::tool(RefreshProductTool::class, ['item_number' => '00263850'])
            ->assertHasErrors()
            ->assertSee('rate_limited');
    }
}
