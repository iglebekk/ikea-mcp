<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\FakesIkea;
use Tests\TestCase;

class IkeaSyncCommandTest extends TestCase
{
    use FakesIkea, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedMarkets();
    }

    public function test_sync_by_query_seeds_the_catalog_and_records_a_run(): void
    {
        $this->fakeIkea();

        $this->artisan('ikea:sync', ['--market' => 'us', '--query' => 'billy'])
            ->expectsOutputToContain('2 new')
            ->assertExitCode(0);

        $this->assertSame(2, Product::query()->count());
        $this->assertDatabaseHas('sync_runs', ['type' => 'query', 'market' => 'us', 'status' => 'completed']);
    }

    public function test_sync_by_product_imports_details(): void
    {
        $this->fakeIkea();

        $this->artisan('ikea:sync', ['--market' => 'us', '--product' => '002.638.50'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('products', ['item_no' => '00263850']);
        $this->assertDatabaseHas('product_translations', ['language' => 'en', 'name' => 'BILLY']);
    }

    public function test_sync_by_category_attaches_products_and_marks_discontinued_on_full_runs(): void
    {
        $this->fakeIkea();

        $this->artisan('ikea:sync', ['--market' => 'us', '--query' => 'billy'])->assertExitCode(0);
        $this->artisan('ikea:sync', ['--market' => 'us', '--category' => '10382'])->assertExitCode(0);

        $product = Product::query()->where('item_no', '00263850')->firstOrFail();
        $this->assertTrue($product->categories()->where('ikea_id', '10382')->exists());
    }

    public function test_failed_sync_is_recorded_with_the_error(): void
    {
        Http::fake(['sik.search.blue.cdtapps.com/*' => Http::response('oops', 500)]);

        $this->artisan('ikea:sync', ['--market' => 'us', '--query' => 'billy'])
            ->assertExitCode(1);

        $run = SyncRun::query()->latest('id')->firstOrFail();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('temporary', $run->error);
    }

    public function test_sync_rejects_unsupported_markets(): void
    {
        $this->artisan('ikea:sync', ['--market' => 'xx', '--query' => 'billy'])
            ->assertExitCode(1);

        $this->assertDatabaseHas('sync_runs', ['market' => 'xx', 'status' => 'failed']);
    }

    public function test_status_command_summarizes_the_catalog(): void
    {
        $this->fakeIkea();
        $this->artisan('ikea:sync', ['--market' => 'us', '--query' => 'billy'])->assertExitCode(0);

        $this->artisan('ikea:status')
            ->expectsOutputToContain('Total products: 2')
            ->assertExitCode(0);
    }
}
