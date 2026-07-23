<?php

namespace Tests\Feature;

use App\Exceptions\IkeaException;
use App\Services\IkeaApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class IkeaApiTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    public function test_item_numbers_are_normalized_from_all_common_formats(): void
    {
        $this->assertSame('00263850', IkeaApi::normalizeItemNo('00263850'));
        $this->assertSame('00263850', IkeaApi::normalizeItemNo('002.638.50'));
        $this->assertSame('00263850', IkeaApi::normalizeItemNo('002-638-50'));
        $this->assertSame('49903093', IkeaApi::normalizeItemNo('s49903093'));
        $this->assertSame('00263850', IkeaApi::normalizeItemNo('https://www.ikea.com/us/en/p/billy-bookcase-white-00263850/'));
    }

    public function test_invalid_item_numbers_are_rejected(): void
    {
        $this->expectException(IkeaException::class);
        $this->expectExceptionMessage('Invalid IKEA item number');

        IkeaApi::normalizeItemNo('12345');
    }

    public function test_unsupported_market_is_rejected(): void
    {
        try {
            app(IkeaApi::class)->validateMarket('xx', 'en');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::MARKET_UNSUPPORTED, $e->reason);
        }
    }

    public function test_unsupported_language_for_market_is_rejected(): void
    {
        try {
            app(IkeaApi::class)->validateMarket('no', 'de');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::LANGUAGE_UNSUPPORTED, $e->reason);
        }
    }

    public function test_search_results_are_normalized(): void
    {
        $this->fakeIkea();

        $result = app(IkeaApi::class)->searchProducts('us', 'en', 'billy');

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['products']);
        $this->assertSame('00263850', $result['products'][0]['item_no']);
        $this->assertSame('BILLY', $result['products'][0]['name']);
        $this->assertEquals(89.99, $result['products'][0]['price']);
        $this->assertEquals(99.99, $result['products'][0]['regular_price']);
        $this->assertSame(['white'], $result['products'][0]['colors']);
        $this->assertSame('10382', $result['products'][0]['category_path'][1]['id']);
        $this->assertSame(['90404097'], $result['products'][0]['variants']);
    }

    public function test_product_details_are_normalized(): void
    {
        $this->fakeIkea();

        $details = app(IkeaApi::class)->productDetails('us', 'en', '002.638.50');

        $this->assertSame('00263850', $details['item_no']);
        $this->assertSame('BILLY', $details['name']);
        $this->assertCount(3, $details['measurements']);
        $this->assertCount(2, $details['images']);
        $this->assertSame('assembly_instruction', $details['documents'][0]['type']);
        $this->assertSame(['90404097'], $details['variants']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/us/en/products/850/00263850.json'));
    }

    public function test_changed_response_format_is_detected(): void
    {
        Http::fake(['sik.search.blue.cdtapps.com/*' => Http::response(['totally' => 'different'])]);

        try {
            app(IkeaApi::class)->searchProducts('us', 'en', 'billy');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::SCHEMA_CHANGED, $e->reason);
        }
    }

    public function test_upstream_404_maps_to_not_found(): void
    {
        Http::fake(['www.ikea.com/*' => Http::response(null, 404)]);

        try {
            app(IkeaApi::class)->productDetails('us', 'en', '00263850');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::NOT_FOUND, $e->reason);
        }
    }

    public function test_upstream_blocking_maps_to_blocked(): void
    {
        Http::fake(['www.ikea.com/*' => Http::response('denied', 403)]);

        try {
            app(IkeaApi::class)->productDetails('us', 'en', '00263850');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::BLOCKED, $e->reason);
        }
    }

    public function test_temporary_upstream_errors_are_retried_then_reported(): void
    {
        Http::fake(['www.ikea.com/*' => Http::response('oops', 500)]);

        try {
            app(IkeaApi::class)->productDetails('us', 'en', '00263850');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::TEMPORARY, $e->reason);
        }

        Http::assertSentCount((int) config('ikea.retries'));
    }

    public function test_local_rate_limit_stops_requests_to_ikea(): void
    {
        config(['ikea.requests_per_minute' => 1]);
        $this->fakeIkea();

        app(IkeaApi::class)->searchProducts('us', 'en', 'billy');

        try {
            app(IkeaApi::class)->searchProducts('us', 'en', 'billy');
            $this->fail('Expected IkeaException');
        } catch (IkeaException $e) {
            $this->assertSame(IkeaException::RATE_LIMITED, $e->reason);
        }

        Http::assertSentCount(1);
    }
}
